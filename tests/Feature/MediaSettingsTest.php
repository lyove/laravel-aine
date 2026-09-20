<?php

namespace Tests\Feature;

use App\Filesystem\MediaStorage;
use App\Models\Media;
use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter as FlysystemAdapter;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MediaSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super_admin']);
        Role::firstOrCreate(['name' => 'editor']);
        Role::firstOrCreate(['name' => 'user']);

        Storage::fake('public');
        Storage::fake('local');
    }

    private function makeSuperAdmin(): User
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin@test.local', 'password' => bcrypt('password')]);
        $user->assignRole('super_admin');

        return $user;
    }

    private function makeProjectOwner(): array
    {
        $user = User::create(['name' => 'Owner', 'email' => 'owner@test.local', 'password' => bcrypt('password')]);
        // Backend-capable role: project owners operate inside the admin area.
        $user->assignRole('editor');

        $project = Project::create([
            'name' => 'Media Proj',
            'slug' => 'media-proj',
            'owner_id' => $user->id,
            'disk' => 'public',
        ]);

        ProjectUser::create(['project_id' => $project->id, 'user_id' => $user->id, 'role' => ProjectUser::ROLE_OWNER]);

        return [$user, $project];
    }

    public function test_settings_update_persists_and_returns_media_configuration(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin);

        $this->postJson('/admin-api/settings/update', [
            'name' => 'Aine',
            'media_max_upload_size' => 16,
            'media_enable_chunk_upload' => true,
            'media_thumbnail_sizes' => '300,600',
            'media_storage_driver' => 'local',
        ])->assertStatus(200);

        $setting = Setting::first();
        $this->assertSame(16, (int) $setting->media_max_upload_size);
        $this->assertTrue((bool) $setting->media_enable_chunk_upload);
        $this->assertSame('300,600', $setting->media_thumbnail_sizes);
        $this->assertSame('local', $setting->media_storage_driver);

        $this->getJson('/admin-api/settings')
            ->assertOk()
            ->assertJsonFragment([
                'media_max_upload_size' => 16,
                'media_enable_chunk_upload' => true,
                'media_thumbnail_sizes' => '300,600',
                'media_storage_driver' => 'local',
            ]);
    }

    public function test_upload_size_limit_from_settings_rejects_oversized_file(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin);

        // 1 MB global cap
        $this->postJson('/admin-api/settings/update', [
            'name' => 'Aine',
            'media_max_upload_size' => 1,
            'media_enable_chunk_upload' => false,
            'media_thumbnail_sizes' => '600',
            'media_storage_driver' => 'local',
        ])->assertStatus(200);

        [$owner, $project] = $this->makeProjectOwner();
        $this->actingAs($owner);

        // 2 MB file must be rejected (max: 1 MB)
        $this->post('/admin-api/media/upload/'.$project->id, [
            'file' => UploadedFile::fake()->createWithContent('big.txt', str_repeat('a', 2 * 1024 * 1024)),
        ])->assertStatus(422);

        // Small file accepted
        $this->post('/admin-api/media/upload/'.$project->id, [
            'file' => UploadedFile::fake()->createWithContent('small.txt', 'hello'),
        ])->assertStatus(200);
    }

    public function test_thumbnails_are_generated_at_every_configured_size(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin);

        $this->postJson('/admin-api/settings/update', [
            'name' => 'Aine',
            'media_max_upload_size' => 8,
            'media_enable_chunk_upload' => false,
            'media_thumbnail_sizes' => '300,600',
            'media_storage_driver' => 'local',
        ])->assertStatus(200);

        [$owner, $project] = $this->makeProjectOwner();
        $this->actingAs($owner);

        $this->post('/admin-api/media/upload/'.$project->id, [
            'file' => UploadedFile::fake()->image('photo.png', 1200, 800),
        ])->assertStatus(200);

        $uuid = $project->uuid;
        // First configured size (300) is the main thumbnail at thumbnails/<name>
        $this->assertTrue(Storage::disk('public')->exists($uuid.'/thumbnails/photo.png'));
        // Remaining sizes (600) live in thumbnails/<size>/<name>
        $this->assertTrue(Storage::disk('public')->exists($uuid.'/thumbnails/600/photo.png'));
        $this->assertFalse(Storage::disk('public')->exists($uuid.'/thumbnails/300/photo.png'));
    }

    public function test_chunked_upload_assembles_file_and_cleans_up_chunks(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin);

        $this->postJson('/admin-api/settings/update', [
            'name' => 'Aine',
            'media_max_upload_size' => 8,
            'media_enable_chunk_upload' => true,
            'media_thumbnail_sizes' => '600',
            'media_storage_driver' => 'local',
        ])->assertStatus(200);

        [$owner, $project] = $this->makeProjectOwner();
        $this->actingAs($owner);

        $identifier = 'testchunk123';

        $this->post('/admin-api/media/upload-chunk/'.$project->id, [
            'file' => UploadedFile::fake()->createWithContent('chunk0.part', str_repeat('a', 10)),
            'identifier' => $identifier,
            'index' => 0,
        ])->assertStatus(200);

        $this->post('/admin-api/media/upload-chunk/'.$project->id, [
            'file' => UploadedFile::fake()->createWithContent('chunk1.part', str_repeat('b', 10)),
            'identifier' => $identifier,
            'index' => 1,
        ])->assertStatus(200);

        $this->post('/admin-api/media/upload-complete/'.$project->id, [
            'identifier' => $identifier,
            'name' => 'assembled.txt',
            'total' => 2,
        ])->assertStatus(200);

        $media = Media::where('project_id', $project->id)->where('name', 'assembled.txt')->first();
        $this->assertNotNull($media);
        $this->assertSame(20, (int) $media->size);

        // Chunks staging directory must be removed
        $this->assertFalse(Storage::disk('local')->exists('chunks/'.$identifier));

        // File landed on the project disk
        $this->assertTrue(Storage::disk('public')->exists($project->uuid.'/assembled.txt'));
    }

    public function test_oss_driver_without_credentials_falls_back_to_local(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin);

        config(['filesystems.disks.oss.access_key_id' => null]);
        config(['filesystems.disks.oss.access_key_secret' => null]);
        config(['filesystems.disks.oss.bucket' => null]);

        $this->postJson('/admin-api/settings/update', [
            'name' => 'Aine',
            'media_max_upload_size' => 8,
            'media_enable_chunk_upload' => false,
            'media_thumbnail_sizes' => '600',
            'media_storage_driver' => 'oss',
        ])->assertStatus(200);

        // Resource reports credentials missing
        $this->getJson('/admin-api/settings')
            ->assertOk()
            ->assertJsonFragment(['media_storage_driver' => 'oss', 'media_storage_configured' => false]);

        [$owner, $project] = $this->makeProjectOwner();
        $this->actingAs($owner);

        $this->post('/admin-api/media/upload/'.$project->id, [
            'file' => UploadedFile::fake()->image('fallback.png', 100, 100),
        ])->assertStatus(200);

        $media = Media::where('project_id', $project->id)->where('name', 'fallback.png')->first();
        $this->assertNotNull($media);
        $this->assertNotSame('oss', $media->disk);
        $this->assertTrue(Storage::disk('public')->exists($project->uuid.'/fallback.png'));
    }

    public function test_oss_config_is_stored_encrypted_and_returned_masked(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin);

        $this->postJson('/admin-api/settings/update', [
            'name' => 'Aine',
            'media_max_upload_size' => 8,
            'media_enable_chunk_upload' => false,
            'media_thumbnail_sizes' => '600',
            'media_storage_driver' => 'oss',
            'media_storage_config' => [
                'access_key_id' => 'LTAI-test',
                'access_key_secret' => 'my-super-secret',
                'bucket' => 'test-bucket',
                'endpoint' => 'oss-cn-hangzhou.aliyuncs.com',
                'custom_url' => '',
            ],
        ])->assertStatus(200);

        $setting = Setting::first();
        // Raw column must not contain plaintext credentials.
        $this->assertStringNotContainsString('my-super-secret', $setting->media_storage_config);
        // It must be decryptable and contain the secret.
        $this->assertStringContainsString('my-super-secret', Crypt::decryptString($setting->media_storage_config));

        // API returns masked secret + plain fields.
        $this->getJson('/admin-api/settings')
            ->assertOk()
            ->assertJsonPath('media_storage_config.access_key_secret', '••••••••')
            ->assertJsonPath('media_storage_config.access_key_id', 'LTAI-test')
            ->assertJsonPath('media_storage_config.bucket', 'test-bucket')
            ->assertJsonPath('media_storage_configured', true);
    }

    public function test_oss_secret_is_kept_when_submitted_empty(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin);

        $base = [
            'name' => 'Aine',
            'media_max_upload_size' => 8,
            'media_enable_chunk_upload' => false,
            'media_thumbnail_sizes' => '600',
            'media_storage_driver' => 'oss',
        ];

        $this->postJson('/admin-api/settings/update', $base + [
            'media_storage_config' => [
                'access_key_id' => 'LTAI-test',
                'access_key_secret' => 'first-secret',
                'bucket' => 'test-bucket',
                'endpoint' => 'oss-cn-hangzhou.aliyuncs.com',
                'custom_url' => '',
            ],
        ])->assertStatus(200);

        // Submit again with an empty secret: it must be retained.
        $this->postJson('/admin-api/settings/update', $base + [
            'media_storage_config' => [
                'access_key_id' => 'LTAI-test',
                'access_key_secret' => '',
                'bucket' => 'test-bucket',
                'endpoint' => 'oss-cn-hangzhou.aliyuncs.com',
                'custom_url' => '',
            ],
        ])->assertStatus(200);

        $setting = Setting::first();
        $this->assertStringContainsString('first-secret', Crypt::decryptString($setting->media_storage_config));

        $this->getJson('/admin-api/settings')
            ->assertOk()
            ->assertJsonPath('media_storage_config.access_key_secret', '••••••••');
    }

    public function test_env_credentials_override_saved_oss_config(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin);

        // Save config in DB first.
        $this->postJson('/admin-api/settings/update', [
            'name' => 'Aine',
            'media_max_upload_size' => 8,
            'media_enable_chunk_upload' => false,
            'media_thumbnail_sizes' => '600',
            'media_storage_driver' => 'oss',
            'media_storage_config' => [
                'access_key_id' => 'DB-KEY',
                'access_key_secret' => 'db-secret',
                'bucket' => 'db-bucket',
                'endpoint' => 'oss-cn-hangzhou.aliyuncs.com',
                'custom_url' => '',
            ],
        ])->assertStatus(200);

        // Then an .env value must win over the DB value.
        config(['filesystems.disks.oss.access_key_id' => 'ENV-KEY']);
        config(['filesystems.disks.oss.access_key_secret' => 'env-secret']);
        config(['filesystems.disks.oss.bucket' => 'env-bucket']);
        config(['filesystems.disks.oss.endpoint' => 'oss-cn-beijing.aliyuncs.com']);
        config(['filesystems.disks.oss.url' => '']);

        $this->getJson('/admin-api/settings')
            ->assertOk()
            ->assertJsonPath('media_storage_config.access_key_id', 'ENV-KEY')
            ->assertJsonPath('media_storage_config.bucket', 'env-bucket');
    }

    public function test_media_storage_test_connection_endpoint_reports_failure_for_bad_credentials(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin);

        // A loopback port with no listener fails fast without external network.
        $this->postJson('/admin-api/settings/test-media-storage', [
            'driver' => 'oss',
            'config' => [
                'access_key_id' => 'fake-key',
                'access_key_secret' => 'fake-secret',
                'bucket' => 'x',
                'endpoint' => 'http://127.0.0.1:9',
                'custom_url' => '',
            ],
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message']);
    }

    public function test_media_storage_configured_requires_all_required_fields(): void
    {
        config(['filesystems.disks.oss' => [
            'access_key_id' => '', 'access_key_secret' => '', 'bucket' => '', 'endpoint' => '', 'url' => '',
        ]]);

        $this->assertFalse(MediaStorage::isConfigured('oss'));

        config(['filesystems.disks.oss' => [
            'access_key_id' => 'a', 'access_key_secret' => 'b', 'bucket' => 'c', 'endpoint' => 'd', 'url' => '',
        ]]);

        $this->assertTrue(MediaStorage::isConfigured('oss'));
    }

    public function test_driver_registry_includes_all_cloud_providers(): void
    {
        $drivers = MediaStorage::drivers();

        foreach (['local', 'oss', 'cos', 'qiniu', 's3'] as $key) {
            $this->assertArrayHasKey($key, $drivers);
            $this->assertArrayHasKey('label', $drivers[$key]);
        }

        // Secrets are flagged so they are masked and preserved on empty submit.
        $this->assertTrue($drivers['oss']['fields']['access_key_secret']['secret']);
        $this->assertTrue($drivers['cos']['fields']['secret_key']['secret']);
        $this->assertTrue($drivers['qiniu']['fields']['secret_key']['secret']);
        $this->assertTrue($drivers['s3']['fields']['secret']['secret']);

        // S3 path-style endpoint is rendered as a checkbox.
        $this->assertSame('checkbox', $drivers['s3']['fields']['use_path_style_endpoint']['type'] ?? '');
    }

    public function test_cos_config_is_stored_encrypted_and_returned_masked(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin);

        $this->postJson('/admin-api/settings/update', [
            'name' => 'Aine',
            'media_max_upload_size' => 8,
            'media_enable_chunk_upload' => false,
            'media_thumbnail_sizes' => '600',
            'media_storage_driver' => 'cos',
            'media_storage_config' => [
                'secret_id' => 'AKID-test',
                'secret_key' => 'cos-secret-999',
                'bucket' => 'my-bucket-1250000000',
                'region' => 'ap-guangzhou',
                'custom_url' => '',
            ],
        ])->assertStatus(200);

        $setting = Setting::first();
        $this->assertStringNotContainsString('cos-secret-999', $setting->media_storage_config);
        $this->assertStringContainsString('cos-secret-999', Crypt::decryptString($setting->media_storage_config));

        $this->getJson('/admin-api/settings')
            ->assertOk()
            ->assertJsonPath('media_storage_config.secret_key', '••••••••')
            ->assertJsonPath('media_storage_config.secret_id', 'AKID-test')
            ->assertJsonPath('media_storage_config.bucket', 'my-bucket-1250000000')
            ->assertJsonPath('media_storage_configured', true);
    }

    public function test_each_cloud_driver_disk_can_be_built(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin);

        // .env values must be empty so DB config is used.
        config(['filesystems.disks.oss' => [
            'access_key_id' => '', 'access_key_secret' => '', 'bucket' => '', 'endpoint' => '', 'url' => '',
        ]]);
        config(['filesystems.disks.s3.key' => '']);

        $this->postJson('/admin-api/settings/update', [
            'name' => 'Aine', 'media_max_upload_size' => 8,
            'media_enable_chunk_upload' => false, 'media_thumbnail_sizes' => '600',
            'media_storage_driver' => 'cos',
            'media_storage_config' => ['secret_id' => 'AKID', 'secret_key' => 'sk', 'bucket' => 'b', 'region' => 'ap-guangzhou', 'custom_url' => ''],
        ])->assertStatus(200);

        $this->postJson('/admin-api/settings/update', [
            'name' => 'Aine', 'media_max_upload_size' => 8,
            'media_enable_chunk_upload' => false, 'media_thumbnail_sizes' => '600',
            'media_storage_driver' => 'qiniu',
            'media_storage_config' => ['access_key' => 'ak', 'secret_key' => 'sk', 'bucket' => 'b', 'custom_url' => 'https://cdn.example.com'],
        ])->assertStatus(200);

        $this->postJson('/admin-api/settings/update', [
            'name' => 'Aine', 'media_max_upload_size' => 8,
            'media_enable_chunk_upload' => false, 'media_thumbnail_sizes' => '600',
            'media_storage_driver' => 's3',
            'media_storage_config' => ['key' => 'k', 'secret' => 's', 'bucket' => 'b', 'region' => 'us-east-1', 'endpoint' => 'http://127.0.0.1:9000', 'url' => 'http://127.0.0.1:9000/b', 'use_path_style_endpoint' => true],
        ])->assertStatus(200);

        foreach (['cos', 'qiniu', 's3'] as $driver) {
            $disk = MediaStorage::disk($driver);
            $valid = is_a($disk, FilesystemAdapter::class)
                || is_a($disk, FlysystemAdapter::class)
                || is_a($disk, Filesystem::class);
            $this->assertTrue($valid, "disk($driver) should build without connecting");
        }
    }
}
