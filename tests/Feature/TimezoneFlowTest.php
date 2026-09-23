<?php

namespace Tests\Feature;

use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TimezoneFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super_admin']);
        Role::firstOrCreate(['name' => 'editor']);
        Role::firstOrCreate(['name' => 'user']);
    }

    private function makeUser(string $email = 'user@test.local'): User
    {
        $user = User::create(['name' => 'User', 'email' => $email, 'password' => bcrypt('password')]);
        $user->assignRole('editor');

        return $user;
    }

    public function test_project_creation_persists_timezone_and_defaults_to_utc(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        // 带时区创建 -> 保存前端传入的浏览器时区
        $this->postJson('/admin-api/projects', [
            'name' => 'TZ Project',
            'default_locale' => 'en',
            'timezone' => 'Asia/Shanghai',
        ])->assertStatus(200);
        $project = Project::where('slug', 'tz-project')->firstOrFail();
        $this->assertSame('Asia/Shanghai', $project->timezone);

        // 不传时区 -> 后端兜底 UTC
        $this->postJson('/admin-api/projects', [
            'name' => 'NoTZ',
            'default_locale' => 'en',
        ])->assertStatus(200);
        $p2 = Project::where('slug', 'notz')->firstOrFail();
        $this->assertSame('UTC', $p2->timezone);
    }

    public function test_preferences_update_persists_timezone(): void
    {
        $user = $this->makeUser();
        $project = Project::create(['name' => 'Mine', 'slug' => 'mine', 'owner_id' => $user->id]);
        ProjectUser::create(['project_id' => $project->id, 'user_id' => $user->id, 'role' => ProjectUser::ROLE_OWNER]);

        $this->actingAs($user);
        $this->postJson('/admin-api/projects/update/' . $project->id, [
            'name' => 'Mine',
            'slug' => 'mine',
            'timezone' => 'Europe/Paris',
        ])->assertStatus(200);

        $this->assertSame('Europe/Paris', $project->fresh()->timezone);
    }

    public function test_project_resource_exposes_all_preferences_fields(): void
    {
        $user = $this->makeUser();
        $project = Project::create([
            'name' => 'Mine',
            'slug' => 'mine',
            'owner_id' => $user->id,
            'timezone' => 'Asia/Tokyo',
            'copyright' => '© Test',
            'filing_info' => 'ICP-123',
            'language_direction' => 'ltr',
            'logo_url' => '/logo.png',
            'favicon_url' => '/favicon.ico',
            'custom_header' => '<meta>',
            'custom_footer' => '<footer>',
            'seo_title' => 'SEO Title',
            'seo_description' => 'SEO Desc',
            'seo_keywords' => 'a,b',
            'analytics_script' => 'GA',
            'contact_address' => 'Addr',
            'contact_email' => 'a@b.com',
            'contact_phone' => '123',
            'contact_text' => 'Text',
        ]);

        $data = (new ProjectResource($project))->resolve();

        // Preferences 全量字段
        $this->assertSame('Asia/Tokyo', $data['timezone']);
        $this->assertSame('© Test', $data['copyright']);
        $this->assertSame('ICP-123', $data['filing_info']);
        $this->assertSame('ltr', $data['language_direction']);
        $this->assertSame('/logo.png', $data['logo_url']);
        $this->assertSame('/favicon.ico', $data['favicon_url']);
        $this->assertSame('<meta>', $data['custom_header']);
        $this->assertSame('<footer>', $data['custom_footer']);
        $this->assertSame('SEO Title', $data['seo_title']);
        $this->assertSame('SEO Desc', $data['seo_description']);
        $this->assertSame('a,b', $data['seo_keywords']);
        $this->assertSame('GA', $data['analytics_script']);
        $this->assertSame('Addr', $data['contact_address']);
        $this->assertSame('a@b.com', $data['contact_email']);
        $this->assertSame('123', $data['contact_phone']);
        $this->assertSame('Text', $data['contact_text']);

        // 原有核心字段不受影响
        $this->assertSame('mine', $data['slug']);
        $this->assertSame('Mine', $data['name']);
    }
}
