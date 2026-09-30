<?php

namespace App\Aine;

use App\Models\Project;
use App\Models\ProjectTheme;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Theme Service — manages theme discovery, registration, and runtime configuration.
 *
 * Theme packages live under:
 *   resources/js/admin/themes/{slug}/
 *
 * Each package MUST contain a manifest.json:
 * {
 *   "name": "CMS",
 *   "description": "...",
 *   "version": "1.0.0",
 *   "author": "Aine",
 *   "icon": "fa-newspaper",
 *   "preview_image": null,
 *   "design_tokens": { "--color-primary": "#4f46e5", ... },
 *   "view_overrides": {
 *     "projects.content.list": "./Content/List/index.vue",
 *     "projects.content.list.articles": "./Content/Articles/List/index.vue"
 *   }
 * }
 *
 * The view_overrides map route names to relative component paths inside the
 * theme's directory. The frontend theme engine resolves these to dynamic imports.
 */
class ThemeService
{
    /**
     * Base directory for theme packages (relative to resources/js/admin/).
     * Themes live in their own directory, separate from shared views.
     */
    private const THEMES_DIR = 'themes';

    /**
     * Scan the filesystem for theme packages and sync them to the database.
     * Returns the list of themes that were discovered/updated.
     */
    public function discoverAndSync(): array
    {
        $basePath = resource_path('js/admin/' . self::THEMES_DIR);
        $discovered = [];

        if (!File::isDirectory($basePath)) {
            Log::warning("ThemeService: themes directory not found: {$basePath}");
            return [];
        }

        foreach (File::directories($basePath) as $dir) {
            $slug = basename($dir);

            $manifestPath = $dir . '/manifest.json';
            if (!File::exists($manifestPath)) {
                Log::info("ThemeService: skipping {$slug} (no manifest.json)");
                continue;
            }

            $manifest = json_decode(File::get($manifestPath), true);
            if (!$manifest || !isset($manifest['name'])) {
                Log::warning("ThemeService: invalid manifest.json in {$slug}");
                continue;
            }

            $designTokens = $manifest['design_tokens'] ?? null;
            $viewOverrides = $manifest['view_overrides'] ?? null;

            $discovered[$slug] = [
                'slug' => $slug,
                'name' => $manifest['name'],
                'description' => $manifest['description'] ?? null,
                'version' => $manifest['version'] ?? '1.0.0',
                'author' => $manifest['author'] ?? null,
                'icon' => $manifest['icon'] ?? null,
                'preview_image' => $manifest['preview_image'] ?? null,
                'asset_path' => self::THEMES_DIR . '/' . $slug,
                'design_tokens' => $designTokens,
                'view_overrides' => $viewOverrides,
            ];
        }

        // Sync to database: create or update
        foreach ($discovered as $slug => $data) {
            ProjectTheme::updateOrCreate(
                ['slug' => $slug],
                $data
            );
        }

        // Mark themes that no longer exist on disk as inactive
        ProjectTheme::whereNotIn('slug', array_keys($discovered))
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return $discovered;
    }

    /**
     * Read view overrides from a theme's config.js file (legacy support).
     * This parses the config.js to extract the views map for themes that
     * haven't migrated to manifest.json yet.
     */
    public function readConfigJs(ProjectTheme $theme): array
    {
        $configPath = resource_path('js/admin/' . $theme->asset_path . '/config.js');

        if (!File::exists($configPath)) {
            return [];
        }

        $content = File::get($configPath);

        // Parse the views object from config.js using regex
        // Matches patterns like: 'route.name': () => import('./path/to/Component.vue')
        $views = [];
        if (preg_match('/views\s*:\s*\{([^}]+(?:\{[^}]*\}[^}]*)*)\}/s', $content, $match)) {
            $viewsBlock = $match[1];
            preg_match_all(
                "/['\"]([^'\"]+)['\"]\s*:\s*\(\)\s*=>\s*import\(['\"]([^'\"]+)['\"]\)/",
                $viewsBlock,
                $matches,
                PREG_SET_ORDER
            );
            foreach ($matches as $m) {
                $views[$m[1]] = $m[2];
            }
        }

        return $views;
    }

    /**
     * Get the complete theme configuration for a project.
     * This is what the frontend theme engine consumes.
     */
    public function getProjectThemeConfig(Project $project): ?array
    {
        $theme = $project->theme;

        if (!$theme || !$theme->is_active) {
            return null;
        }

        return [
            'slug' => $theme->slug,
            'name' => $theme->name,
            'version' => $theme->version,
            'icon' => $theme->icon,
            'asset_path' => $theme->asset_path,
            'design_tokens' => $theme->mergedTokens($project),
            'view_overrides' => $theme->resolvedViewOverrides(),
        ];
    }

    /**
     * Get all available themes for the admin UI (theme selection list).
     */
    public function getAvailableThemes(): array
    {
        return ProjectTheme::active()
            ->get()
            ->map(fn (ProjectTheme $theme) => [
                'id' => $theme->id,
                'slug' => $theme->slug,
                'name' => $theme->name,
                'description' => $theme->description,
                'version' => $theme->version,
                'author' => $theme->author,
                'icon' => $theme->icon,
                'preview_image' => $theme->preview_image,
                'design_tokens' => $theme->design_tokens,
                'projects_count' => $theme->projects()->count(),
            ])
            ->toArray();
    }

    /**
     * Apply a theme to a project.
     */
    public function applyTheme(Project $project, int $themeId): bool
    {
        $theme = ProjectTheme::findOrFail($themeId);

        if (!$theme->is_active) {
            return false;
        }

        unset($project->my_role, $project->is_readonly, $project->raw_name, $project->raw_description);

        $project->update([
            'theme_id' => $theme->id,
        ]);

        return true;
    }

    /**
     * Update per-project theme configuration (design token overrides).
     */
    public function updateThemeConfig(Project $project, array $config): void
    {
        $project->update(['theme_config' => $config]);
    }

    /**
     * Get the default design tokens for a theme.
     */
    public function getDefaultTokens(string $themeSlug): array
    {
        $theme = ProjectTheme::where('slug', $themeSlug)->first();
        return $theme?->design_tokens ?? [];
    }
}
