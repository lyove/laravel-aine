<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Project;
use Illuminate\Http\Request;
use App\Support\AdminPath;

/**
 * Block write operations (POST/PUT/PATCH/DELETE) on inactive projects.
 */
class BlockInactiveProjectWrites
{
    /**
     * URI suffixes that should be exempt from the read-only check.
     * These allow re-activating or configuring an inactive project.
     */
    protected array $exemptSuffixes = [
        'projects/toggle-status/*',
        'projects/update/*',
        'content/get-selected-records/*',
        'content/get-selected-files/*',
    ];

    public function handle(Request $request, Closure $next)
    {
        // Only block write methods
        if ($request->isMethod('GET') || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return $next($request);
        }

        // Check if this route is exempt (prefix follows the configurable admin API slug)
        $api = AdminPath::apiSlug();
        foreach ($this->exemptSuffixes as $suffix) {
            if ($request->is($api.'/'.$suffix)) {
                return $next($request);
            }
        }

        // Extract project_id from route parameters
        $projectId = $request->route('project_id') ?? $request->route('id');

        if (!$projectId) {
            return $next($request);
        }

        // Load the project and check its status
        $project = Project::find($projectId);

        if (!$project) {
            return $next($request);
        }

        if (!$project->isActive()) {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => 'This project is inactive and in read-only mode. Please reactivate it first to make changes.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
