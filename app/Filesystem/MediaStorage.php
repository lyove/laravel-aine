<?php

namespace App\Filesystem;

use App\Models\Setting;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use OSS\OssClient;
use Qcloud\Cos\Client;
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Throwable;

/**
 * Central registry for media storage drivers.
 */
class MediaStorage
{
    /**
     * Registered drivers and their configuration schemas.
     *
     * field flags:
     *   required  - must be non-empty for the driver to be usable
     *   secret    - masked in API responses, kept unchanged when empty on save
     *   default   - value pre-filled in the UI
     *   placeholder - input placeholder
     *   hint      - helper text shown under the input
     *
     * @return array<string, array{label: string, description?: string, fields: array<string, array<string, mixed>>}>
     */
    public static function drivers(): array
    {
        return [
            'local' => [
                'label' => 'Local storage',
                'description' => 'Store files on the server disk (public/storage).',
                'fields' => [],
            ],
            'oss' => [
                'label' => 'Aliyun OSS',
                'description' => 'Store files in Alibaba Cloud Object Storage Service.',
                'fields' => [
                    'access_key_id' => [
                        'label' => 'AccessKey ID',
                        'required' => true,
                        'secret' => false,
                        'placeholder' => 'LTAI5t...',
                    ],
                    'access_key_secret' => [
                        'label' => 'AccessKey Secret',
                        'required' => true,
                        'secret' => true,
                        'placeholder' => '••••••••',
                        'hint' => 'Leave empty to keep the saved value.',
                    ],
                    'bucket' => [
                        'label' => 'Bucket',
                        'required' => true,
                        'secret' => false,
                        'placeholder' => 'my-media-bucket',
                        'hint' => 'The bucket created in the OSS console.',
                    ],
                    'endpoint' => [
                        'label' => 'Endpoint',
                        'required' => true,
                        'secret' => false,
                        'default' => 'oss-cn-hangzhou.aliyuncs.com',
                        'placeholder' => 'oss-cn-hangzhou.aliyuncs.com',
                        'hint' => 'Region access address, e.g. oss-cn-hangzhou.aliyuncs.com.',
                    ],
                    'custom_url' => [
                        'label' => 'Custom URL',
                        'required' => false,
                        'secret' => false,
                        'placeholder' => 'https://cdn.example.com',
                        'hint' => 'Optional. Public URL prefix for served files (e.g. bound CDN domain).',
                    ],
                ],
            ],
            'cos' => [
                'label' => 'Tencent Cloud COS',
                'description' => 'Store files in Tencent Cloud Object Storage.',
                'fields' => [
                    'secret_id' => [
                        'label' => 'SecretId',
                        'required' => true,
                        'secret' => false,
                        'placeholder' => 'AKID...',
                    ],
                    'secret_key' => [
                        'label' => 'SecretKey',
                        'required' => true,
                        'secret' => true,
                        'placeholder' => '••••••••',
                        'hint' => 'Leave empty to keep the saved value.',
                    ],
                    'bucket' => [
                        'label' => 'Bucket',
                        'required' => true,
                        'secret' => false,
                        'placeholder' => 'my-bucket-1250000000',
                        'hint' => 'Bucket name from the COS console, e.g. name-APPID.',
                    ],
                    'region' => [
                        'label' => 'Region',
                        'required' => true,
                        'secret' => false,
                        'default' => 'ap-guangzhou',
                        'placeholder' => 'ap-guangzhou',
                        'hint' => 'Bucket region, e.g. ap-guangzhou.',
                    ],
                    'custom_url' => [
                        'label' => 'Custom URL',
                        'required' => false,
                        'secret' => false,
                        'placeholder' => 'https://cdn.example.com',
                        'hint' => 'Optional. Public URL prefix for served files.',
                    ],
                ],
            ],
            'qiniu' => [
                'label' => 'Qiniu Cloud Kodo',
                'description' => 'Store files in Qiniu Cloud Object Storage.',
                'fields' => [
                    'access_key' => [
                        'label' => 'AccessKey',
                        'required' => true,
                        'secret' => false,
                        'placeholder' => '...',
                    ],
                    'secret_key' => [
                        'label' => 'SecretKey',
                        'required' => true,
                        'secret' => true,
                        'placeholder' => '••••••••',
                        'hint' => 'Leave empty to keep the saved value.',
                    ],
                    'bucket' => [
                        'label' => 'Bucket',
                        'required' => true,
                        'secret' => false,
                        'placeholder' => 'my-bucket',
                    ],
                    'custom_url' => [
                        'label' => 'Domain',
                        'required' => true,
                        'secret' => false,
                        'placeholder' => 'https://cdn.example.com',
                        'hint' => 'Public access domain bound to the bucket (required to serve files).',
                    ],
                ],
            ],
            's3' => [
                'label' => 'AWS S3 / S3-compatible',
                'description' => 'Store files in AWS S3 or any S3-compatible service (MinIO, etc.).',
                'fields' => [
                    'key' => [
                        'label' => 'Access Key',
                        'required' => true,
                        'secret' => false,
                        'placeholder' => 'AKIA...',
                    ],
                    'secret' => [
                        'label' => 'Secret Key',
                        'required' => true,
                        'secret' => true,
                        'placeholder' => '••••••••',
                        'hint' => 'Leave empty to keep the saved value.',
                    ],
                    'bucket' => [
                        'label' => 'Bucket',
                        'required' => true,
                        'secret' => false,
                        'placeholder' => 'my-bucket',
                    ],
                    'region' => [
                        'label' => 'Region',
                        'required' => true,
                        'secret' => false,
                        'default' => 'us-east-1',
                        'placeholder' => 'us-east-1',
                    ],
                    'endpoint' => [
                        'label' => 'Endpoint',
                        'required' => false,
                        'secret' => false,
                        'placeholder' => 'https://minio.example.com',
                        'hint' => 'Optional. Required for S3-compatible services such as MinIO.',
                    ],
                    'url' => [
                        'label' => 'URL',
                        'required' => true,
                        'secret' => false,
                        'placeholder' => 'https://bucket.s3.amazonaws.com',
                        'hint' => 'Public base URL used to serve files.',
                    ],
                    'use_path_style_endpoint' => [
                        'label' => 'Use path-style endpoint',
                        'required' => false,
                        'secret' => false,
                        'type' => 'checkbox',
                        'hint' => 'Enable for MinIO and most S3-compatible services.',
                    ],
                ],
            ],
        ];
    }

    /**
     * The currently selected storage driver from settings.
     */
    public static function activeDriver(): string
    {
        $driver = Setting::first()?->media_storage_driver ?? 'local';

        return in_array($driver, array_keys(self::drivers()), true) ? $driver : 'local';
    }

    /**
     * Raw (decrypted) driver configurations stored in the settings table.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function savedConfig(): array
    {
        $raw = Setting::first()?->media_storage_config;

        if (!$raw) {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($raw), true);

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Effective configuration for a driver: .env values win over saved ones.
     *
     * @return array<string, mixed>
     */
    public static function resolvedConfig(string $driver): array
    {
        if ($driver === 'oss') {
            $env = config('filesystems.disks.oss', []);

            if (!empty($env['access_key_id'])) {
                return [
                    'access_key_id' => $env['access_key_id'],
                    'access_key_secret' => $env['access_key_secret'] ?? '',
                    'bucket' => $env['bucket'] ?? '',
                    'endpoint' => $env['endpoint'] ?? 'oss-cn-hangzhou.aliyuncs.com',
                    'custom_url' => $env['url'] ?? '',
                ];
            }
        }

        if ($driver === 's3') {
            $env = config('filesystems.disks.s3', []);

            if (!empty($env['key'])) {
                return [
                    'key' => $env['key'],
                    'secret' => $env['secret'] ?? '',
                    'bucket' => $env['bucket'] ?? '',
                    'region' => $env['region'] ?? 'us-east-1',
                    'endpoint' => $env['endpoint'] ?? '',
                    'url' => $env['url'] ?? '',
                    'use_path_style_endpoint' => (bool) ($env['use_path_style_endpoint'] ?? false),
                ];
            }
        }

        return self::savedConfig()[$driver] ?? [];
    }

    /**
     * Whether a driver has all required credentials to operate.
     */
    public static function isConfigured(string $driver): bool
    {
        $fields = self::drivers()[$driver]['fields'] ?? [];

        $required = array_keys(array_filter($fields, static fn ($f) => !empty($f['required'])));

        if ($required === []) {
            return true;
        }

        $config = self::resolvedConfig($driver);

        foreach ($required as $key) {
            if (empty($config[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build the Laravel storage instance for a driver using its effective config.
     */
    public static function disk(string $driver): mixed
    {
        if ($driver === 'oss') {
            $config = self::resolvedConfig('oss');

            return Storage::build([
                'driver' => 'oss',
                'access_key_id' => $config['access_key_id'] ?? '',
                'access_key_secret' => $config['access_key_secret'] ?? '',
                'bucket' => $config['bucket'] ?? '',
                'endpoint' => $config['endpoint'] ?? 'oss-cn-hangzhou.aliyuncs.com',
                'url' => $config['custom_url'] ?? '',
                'use_ssl' => (bool) ($config['use_ssl'] ?? false),
            ]);
        }

        if ($driver === 'cos') {
            $config = self::resolvedConfig('cos');

            return Storage::build([
                'driver' => 'cos',
                'secret_id' => $config['secret_id'] ?? '',
                'secret_key' => $config['secret_key'] ?? '',
                'bucket' => $config['bucket'] ?? '',
                'region' => $config['region'] ?? '',
                'custom_url' => $config['custom_url'] ?? '',
            ]);
        }

        if ($driver === 'qiniu') {
            $config = self::resolvedConfig('qiniu');

            // The qiniu SDK emits PHP 8.4+ implicit-nullable deprecations when
            // its classes are first loaded; suppress them so responses are not
            // polluted on PHP 8.4/8.5 servers.
            $previous = error_reporting(E_ALL & ~E_DEPRECATED);

            try {
                return Storage::build([
                    'driver' => 'qiniu',
                    'access_key' => $config['access_key'] ?? '',
                    'secret_key' => $config['secret_key'] ?? '',
                    'bucket' => $config['bucket'] ?? '',
                    'custom_url' => $config['custom_url'] ?? '',
                ]);
            } finally {
                error_reporting($previous);
            }
        }

        if ($driver === 's3') {
            $config = self::resolvedConfig('s3');

            return Storage::build([
                'driver' => 's3',
                'key' => $config['key'] ?? '',
                'secret' => $config['secret'] ?? '',
                'bucket' => $config['bucket'] ?? '',
                'region' => $config['region'] ?? 'us-east-1',
                'endpoint' => $config['endpoint'] ?? null,
                'url' => $config['url'] ?? '',
                'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? false),
            ]);
        }

        return Storage::disk($driver);
    }

    /**
     * Persist driver configurations, encrypted, into the settings table.
     *
     * @param string $driver
     * @param array<string, mixed> $config
     * @return void
     */
    public static function saveConfig(string $driver, array $config): void
    {
        $setting = Setting::first();

        if (!$setting) {
            return;
        }

        $all = self::savedConfig();
        $all[$driver] = $config;

        $setting->media_storage_driver = $driver;
        $setting->media_storage_config = Crypt::encryptString(json_encode($all));
        $setting->save();
    }

    /**
     * Merge submitted values with saved ones so empty secret fields keep the
     * previous value.
     *
     * @param string $driver
     * @param array<string, mixed> $submitted
     * @return array<string, mixed>
     */
    public static function mergeSecrets(string $driver, array $submitted): array
    {
        $saved = self::resolvedConfig($driver);

        foreach (self::drivers()[$driver]['fields'] ?? [] as $key => $field) {
            if (!empty($field['secret']) && empty($submitted[$key]) && !empty($saved[$key])) {
                $submitted[$key] = $saved[$key];
            }
        }

        return $submitted;
    }

    /**
     * Mask secret fields for API responses.
     *
     * @param string $driver
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function sanitize(string $driver, array $config): array
    {
        $result = [];

        foreach (self::drivers()[$driver]['fields'] ?? [] as $key => $field) {
            if (!empty($field['secret'])) {
                $result[$key] = !empty($config[$key]) ? '••••••••' : null;
            } else {
                $result[$key] = $config[$key] ?? ($field['default'] ?? null);
            }
        }

        return $result;
    }

    /**
     * Verify credentials by making a lightweight request against the provider.
     *
     * @param string $driver
     * @param array<string, mixed> $config
     * @return array{success: bool, message: string}
     */
    public static function testConnection(string $driver, array $config): array
    {
        try {
            if ($driver === 'oss') {
                $client = new OssClient(
                    $config['access_key_id'] ?? '',
                    $config['access_key_secret'] ?? '',
                    $config['endpoint'] ?? '',
                    (bool) ($config['use_ssl'] ?? false),
                );

                $client->listBuckets();

                return ['success' => true, 'message' => 'Connection successful.'];
            }

            if ($driver === 'cos') {
                $client = new Client([
                    'region' => $config['region'] ?? '',
                    'scheme' => 'https',
                    'credentials' => [
                        'secretId' => $config['secret_id'] ?? '',
                        'secretKey' => $config['secret_key'] ?? '',
                    ],
                ]);

                $client->listBuckets();

                return ['success' => true, 'message' => 'Connection successful.'];
            }

            if ($driver === 'qiniu') {
                // Suppress SDK first-load deprecations on PHP 8.4+.
                $previous = error_reporting(E_ALL & ~E_DEPRECATED);

                try {
                    $auth = new Auth($config['access_key'] ?? '', $config['secret_key'] ?? '');
                    $manager = new BucketManager($auth);

                    [$info, $error] = $manager->bucketInfo($config['bucket'] ?? '');

                    if ($error !== null) {
                        return ['success' => false, 'message' => $error->message() ?: 'Connection failed.'];
                    }

                    return ['success' => true, 'message' => 'Connection successful.'];
                } finally {
                    error_reporting($previous);
                }
            }

            if ($driver === 's3') {
                $client = new S3Client([
                    'version' => 'latest',
                    'region' => $config['region'] ?? 'us-east-1',
                    'credentials' => [
                        'key' => $config['key'] ?? '',
                        'secret' => $config['secret'] ?? '',
                    ],
                    'endpoint' => $config['endpoint'] ?? null,
                    'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? false),
                ]);

                $client->listBuckets();

                return ['success' => true, 'message' => 'Connection successful.'];
            }

            return ['success' => false, 'message' => 'Driver does not support connection testing.'];
        } catch (Throwable $e) {
            $message = $e->getMessage() ?: 'Connection failed.';

            if ($message === 'Validation failed') {
                $message = 'Connection failed. Please check the endpoint and credentials.';
            }

            return ['success' => false, 'message' => $message];
        }
    }
}
