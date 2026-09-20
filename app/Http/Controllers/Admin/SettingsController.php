<?php

namespace App\Http\Controllers\Admin;

use App\Aine\AuditLogger;
use App\Filesystem\MediaStorage;
use App\Http\Controllers\Controller;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use App\Support\AdminPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /**
     * Get settings
     *
     * @param \Illuminate\Http\Request $request
     * @return \App\Http\Resources\SettingResource
     */
    public function index(Request $request){
        $setting = Setting::first();
        
        if (!$setting) {
            $setting = Setting::create([
                'name' => config('app.name', __('My Website')),
                'description' => __('Aine is a content management system built with Laravel and Vue.js'),
                'version' => '0.0.1'
            ]);
        }

        return new SettingResource($setting);
    }

    /**
     * Update settings
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
$request->validate([
            'name' => 'required|string|max:255',
            'media_max_upload_size' => 'nullable|integer|min:1|max:2048',
            'media_enable_chunk_upload' => 'nullable|boolean',
            'media_thumbnail_sizes' => 'nullable|string|max:255',
            'media_storage_driver' => 'nullable|in:local,oss,cos,qiniu,s3',
            'media_storage_config' => 'nullable|array',
            'mail_driver' => 'nullable|string|max:20',
            'mail_host' => 'nullable|string|max:255',
            'mail_port' => 'nullable|integer',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_encryption' => 'nullable|string|max:10',
            'mail_from_address' => 'nullable|email|max:255',
            'mail_from_name' => 'nullable|string|max:255',
            // Custom admin entry path. Empty or null resets to the default "admin".
            'admin_path' => [
                'nullable',
                'string',
                'max:60',
                function ($attribute, $value, $fail) {
                    $value = trim((string) $value, '/ ');
                    if ($value !== '' && ! AdminPath::isValidSlug($value)) {
                        $fail(__('Admin path must be a single lowercase slug (letters, digits, hyphens), not a reserved word, and must start with a letter.'));
                    }
                },
            ],
        ]);

        $setting = Setting::first();
        
        if (!$setting) {
            $setting = Setting::create([
                'name' => $request->name,
                'description' => $request->description ?? '',
                'version' => $request->version ?? '',
                'media_max_upload_size' => $request->media_max_upload_size ?? 8,
                'media_enable_chunk_upload' => (bool) $request->media_enable_chunk_upload,
                'media_thumbnail_sizes' => $request->media_thumbnail_sizes ?? '600',
                'media_storage_driver' => $request->media_storage_driver ?? 'local',
            ]);
        } else {
            $setting->name = $request->name;
            $setting->description = $request->description ?? '';
            $setting->version = $request->version ?? '';
            $setting->media_max_upload_size = $request->media_max_upload_size ?? $setting->media_max_upload_size;
            $setting->media_enable_chunk_upload = (bool) $request->media_enable_chunk_upload;
            $setting->media_thumbnail_sizes = $request->media_thumbnail_sizes ?? $setting->media_thumbnail_sizes;
            $setting->media_storage_driver = $request->media_storage_driver ?? $setting->media_storage_driver;
            $setting->mail_driver = $request->mail_driver ?? $setting->mail_driver;
            $setting->mail_host = $request->mail_host ?? $setting->mail_host;
            $setting->mail_port = $request->mail_port ?? $setting->mail_port;
            $setting->mail_username = $request->mail_username ?? $setting->mail_username;
            if ($request->mail_password && $request->mail_password !== '••••••••') {
                $setting->mail_password = $request->mail_password;
            }
            $setting->mail_encryption = $request->mail_encryption ?? $setting->mail_encryption;
            $setting->mail_from_address = $request->mail_from_address ?? $setting->mail_from_address;
            $setting->mail_from_name = $request->mail_from_name ?? $setting->mail_from_name;

            $previousAdminPath = $setting->admin_path;
            $newAdminPath = trim((string) $request->admin_path, '/ ');
            // Empty falls back to the default "admin".
            $setting->admin_path = $newAdminPath === '' ? AdminPath::DEFAULT : $newAdminPath;

            $setting->save();
        }

        $driver = $request->media_storage_driver ?? $setting->media_storage_driver ?? 'local';
        if ($driver !== 'local' && is_array($request->media_storage_config)) {
            $config = MediaStorage::mergeSecrets($driver, $request->media_storage_config);
            MediaStorage::saveConfig($driver, $config);
        }

        $adminPathChanged = isset($previousAdminPath)
            && $previousAdminPath !== $setting->admin_path;

        if ($adminPathChanged) {
            AdminPath::flush();
            Artisan::call('route:clear');
            Artisan::call('view:clear');
        }

        AuditLogger::log('update', 'settings', $setting->id, $setting->name);

        return response()->json([
            'success' => true,
            'admin_path_changed' => $adminPathChanged,
            'admin_path' => AdminPath::slug(),
            'admin_url' => AdminPath::prefix().'/login',
        ]);
    }

    /**
     * Test a media storage driver connection without saving it.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testMediaStorage(Request $request)
    {
        $request->validate([
            'driver' => 'required|in:oss,cos,qiniu,s3',
            'config' => 'required|array',
        ]);

        $config = MediaStorage::mergeSecrets($request->driver, $request->config);

        $result = MediaStorage::testConnection($request->driver, $config);

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
