<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Setting extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = "settings";

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'version',
        'admin_path',
        'media_max_upload_size',
        'media_enable_chunk_upload',
        'media_thumbnail_sizes',
        'media_storage_driver',
        'media_storage_config',
        'mail_driver',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'mail_password',
        'media_storage_config',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'media_enable_chunk_upload' => 'boolean',
        'mail_password' => 'encrypted',
        'media_storage_config' => 'encrypted',
    ];
}
