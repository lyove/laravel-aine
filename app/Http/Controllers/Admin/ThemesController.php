<?php

namespace App\Http\Controllers\Admin;

use App\Aine\ThemeService;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTheme;
use Illuminate\Http\Request;

class ThemesController extends Controller
{
    public function __construct(
        private ThemeService $themeService
    ) {}

    /**
     * List all available themes.
     * Auto-syncs from filesystem if no themes exist.
     */
    public function index()
    {
        $themes = $this->themeService->getAvailableThemes();

        if (empty($themes)) {
            $this->themeService->discoverAndSync();
            $themes = $this->themeService->getAvailableThemes();
        }

        return response()->json($themes);
    }

    /**
     * Get a single theme's details.
     */
    public function show(int $id)
    {
        $theme = ProjectTheme::findOrFail($id);

        return response()->json([
            'id' => $theme->id,
            'slug' => $theme->slug,
            'name' => $theme->name,
            'description' => $theme->description,
            'version' => $theme->version,
            'author' => $theme->author,
            'icon' => $theme->icon,
            'preview_image' => $theme->preview_image,
            'design_tokens' => $theme->design_tokens,
            'view_overrides' => $theme->resolvedViewOverrides(),
            'is_active' => $theme->is_active,
            'projects_count' => $theme->projects()->count(),
        ]);
    }

    /**
     * Apply a theme to a project.
     */
    public function apply(Request $request, int $projectId)
    {
        $request->validate([
            'theme_id' => 'required|integer|exists:project_themes,id',
        ]);

        $project = Project::findOrFail($projectId);

        // Check authorization: only owner/admin can change theme
        $user = auth()->user();
        if (!in_array($project->applyMyRole($user)->my_role, ['owner', 'admin'])) {
            abort(403, 'Only project owners and admins can change the theme.');
        }

        $success = $this->themeService->applyTheme($project, (int) $request->theme_id);

        if (!$success) {
            return response()->json(['message' => 'Theme is not active.'], 422);
        }

        // Reload the project with the theme relationship
        $project->load(['theme', 'collections']);
        $project->presentFor($user);

        return response()->json([
            'message' => 'Theme applied successfully.',
            'project' => $project,
            'theme_config' => $this->themeService->getProjectThemeConfig($project),
        ]);
    }

    /**
     * Get the current theme configuration for a project.
     */
    public function projectConfig(int $projectId)
    {
        $project = Project::with('theme')->findOrFail($projectId);
        $config = $this->themeService->getProjectThemeConfig($project);

        return response()->json([
            'current_theme' => $config,
            'project_theme_config' => $project->theme_config ?? [],
        ]);
    }

    /**
     * Update per-project theme configuration (design token overrides).
     */
    public function updateConfig(Request $request, int $projectId)
    {
        $project = Project::findOrFail($projectId);

        // Check authorization
        $user = auth()->user();
        if (!in_array($project->applyMyRole($user)->my_role, ['owner', 'admin'])) {
            abort(403, 'Only project owners and admins can customize theme settings.');
        }

        $request->validate([
            'theme_config' => 'required|array',
        ]);

        $this->themeService->updateThemeConfig($project, $request->theme_config);

        return response()->json([
            'message' => 'Theme configuration updated.',
            'theme_config' => $project->fresh()->theme_config,
            'merged_tokens' => $project->theme?->mergedTokens($project) ?? [],
        ]);
    }

    /**
     * Re-scan the filesystem for theme packages and sync to database.
     */
    public function sync()
    {
        $discovered = $this->themeService->discoverAndSync();

        return response()->json([
            'message' => count($discovered) . ' theme(s) discovered and synced.',
            'themes' => $this->themeService->getAvailableThemes(),
        ]);
    }

    /**
     * Toggle a theme's active status (super admin only).
     */
    public function toggleStatus(int $id)
    {
        $theme = ProjectTheme::findOrFail($id);
        $theme->update(['is_active' => !$theme->is_active]);

        return response()->json([
            'message' => $theme->is_active ? 'Theme activated.' : 'Theme deactivated.',
            'is_active' => $theme->is_active,
        ]);
    }
}
