<?php

namespace App\Http\Resources;

use App\Filesystem\MediaStorage;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $driver = $this->media_storage_driver ?? 'local';

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'admin_path' => $this->admin_path ?? '',
            'media_max_upload_size' => $this->media_max_upload_size ?? 8,
            'media_enable_chunk_upload' => (bool) $this->media_enable_chunk_upload,
            'media_thumbnail_sizes' => $this->media_thumbnail_sizes ?? '600',
            'media_storage_driver' => $driver,
            'media_storage_config' => MediaStorage::sanitize($driver, MediaStorage::resolvedConfig($driver)),
            'media_storage_drivers' => MediaStorage::drivers(),
            'media_storage_configured' => MediaStorage::isConfigured($driver),
            'mail_driver' => $this->mail_driver ?? 'smtp',
            'mail_host' => $this->mail_host,
            'mail_port' => $this->mail_port,
            'mail_username' => $this->mail_username,
            'mail_password' => $this->mail_password ? '••••••••' : '',
            'mail_encryption' => $this->mail_encryption,
            'mail_from_address' => $this->mail_from_address,
            'mail_from_name' => $this->mail_from_name,
        ];
    }
}
