<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTemplate extends Model
{
    protected $table = 'project_templates';

    protected $fillable = ['name', 'slug', 'description', 'icon', 'collections', 'is_active', 'order'];

    protected $casts = [
        'collections' => 'array',
        'is_active'   => 'boolean',
    ];
}
