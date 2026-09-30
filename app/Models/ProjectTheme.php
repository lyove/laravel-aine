<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTheme extends Model
{
    protected $table = 'project_themes';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'version',
        'author',
        'icon',
        'preview_image',
        'asset_path',
        'design_tokens',
        'view_overrides',
        'is_active',
    ];

    protected $casts = [
        'design_tokens' => 'array',
        'view_overrides' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Projects using this theme.
     */
    public function projects()
    {
        return $this->hasMany(Project::class, 'theme_id');
    }

    /**
     * Scope to active themes only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Resolve the merged design tokens: theme defaults + per-project overrides.
     */
    public function mergedTokens(?Project $project): array
    {
        $defaults = is_array($this->design_tokens) ? $this->design_tokens : [];

        if (!$project) {
            return $defaults;
        }

        $overrides = is_array($project->theme_config) ? $project->theme_config : [];

        return array_merge($defaults, $overrides);
    }

    /**
     * Build the full view-override map from the theme's explicit config
     * and the convention-based config.js inside the asset_path directory.
     */
    public function resolvedViewOverrides(): array
    {
        $explicit = $this->view_overrides ?? [];

        // If the theme has explicit overrides stored in DB, use them.
        if (!empty($explicit)) {
            return $explicit;
        }

        // Otherwise, fall back to reading the config.js at runtime via ThemeService.
        return app(\App\Aine\ThemeService::class)->readConfigJs($this);
    }
}
