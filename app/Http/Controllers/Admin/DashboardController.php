<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Media;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics for the current user.
     */
    public function stats()
    {
        $user = Auth::user();

        // Get user's accessible projects
        $accessibleProjectIds = $this->getUserAccessibleProjectIds($user);

        // Overview statistics
        $stats = [
            'my_projects' => Project::whereIn('id', $accessibleProjectIds)->count(),
            'total_content' => Content::whereIn('project_id', $accessibleProjectIds)->count(),
            'media_files' => Media::whereIn('project_id', $accessibleProjectIds)->count(),
            'pending_comments' => Content::whereIn('project_id', $accessibleProjectIds)
                ->whereHas('collection', function ($query) {
                    $query->where('slug', 'comments');
                })
                ->whereHas('meta', function ($query) {
                    $query->where('field_name', 'status')->where('value', 'pending');
                })
                ->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Get user's projects with basic info.
     */
    public function myProjects(Request $request)
    {
        $user = Auth::user();
        $limit = $request->get('limit', 5);

        $accessibleProjectIds = $this->getUserAccessibleProjectIds($user);

        $projects = Project::whereIn('id', $accessibleProjectIds)
            ->withCount(['collections', 'content'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($project) use ($user) {
                $project->presentFor($user);
                return $project;
            });

        return response()->json($projects);
    }

    /**
     * Get todo items for the user.
     */
    public function todos()
    {
        $user = Auth::user();
        $accessibleProjectIds = $this->getUserAccessibleProjectIds($user);

        $todos = [];

        // Pending comments
        $pendingComments = Content::whereIn('project_id', $accessibleProjectIds)
            ->whereHas('collection', function ($query) {
                $query->where('slug', 'comments');
            })
            ->whereHas('meta', function ($query) {
                $query->where('field_name', 'status')->where('value', 'pending');
            })
            ->count();

        if ($pendingComments > 0) {
            $commentProject = Content::whereIn('project_id', $accessibleProjectIds)
                ->whereHas('collection', function ($query) {
                    $query->where('slug', 'comments');
                })
                ->whereHas('meta', function ($query) {
                    $query->where('field_name', 'status')->where('value', 'pending');
                })
                ->with('collection')
                ->first();
            
            $todos[] = [
                'type' => 'comments',
                'count' => $pendingComments,
                'message' => trans_choice('{1} :count comment needs review|[2,*] :count comments need review', $pendingComments, ['count' => $pendingComments]),
                'action' => $commentProject ? "/project/{$commentProject->project_id}/comments" : '/projects',
            ];
        }

        // Draft content (created by current user)
        $draftContent = Content::whereIn('project_id', $accessibleProjectIds)
            ->where('created_by', $user->id)
            ->whereNull('published_at')
            ->count();

        if ($draftContent > 0) {
            $draftContent2 = Content::whereIn('project_id', $accessibleProjectIds)
                ->where('created_by', $user->id)
                ->whereNull('published_at')
                ->first();
            
            $todos[] = [
                'type' => 'drafts',
                'count' => $draftContent,
                'message' => trans_choice('{1} :count draft content|[2,*] :count draft contents', $draftContent, ['count' => $draftContent]),
                'action' => $draftContent2 ? "/project/{$draftContent2->project_id}/content/{$draftContent2->collection_id}?filter=draft" : '/projects',
            ];
        }

        // Trashed content
        $trashedContent = Content::whereIn('project_id', $accessibleProjectIds)
            ->onlyTrashed()
            ->count();

        if ($trashedContent > 0) {
            $trashedContent2 = Content::whereIn('project_id', $accessibleProjectIds)
                ->onlyTrashed()
                ->first();
            
            $todos[] = [
                'type' => 'trashed',
                'count' => $trashedContent,
                'message' => trans_choice('{1} :count item in trash|[2,*] :count items in trash', $trashedContent, ['count' => $trashedContent]),
                'action' => $trashedContent2 ? "/project/{$trashedContent2->project_id}/content/{$trashedContent2->collection_id}?filter=trashed" : '/projects',
            ];
        }

        return response()->json($todos);
    }

    /**
     * Get recent activities for the user.
     */
    public function recentActivities(Request $request)
    {
        $user = Auth::user();
        $limit = $request->get('limit', 10);

        $accessibleProjectIds = $this->getUserAccessibleProjectIds($user);

        // Get recent content activities
        $activities = Content::whereIn('project_id', $accessibleProjectIds)
            ->with(['collection.project', 'creator', 'meta'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($content) {
                $action = $content->trashed() ? 'deleted' : ($content->published_at ? 'published' : 'updated');

                return [
                    'id' => $content->id,
                    'action' => $action,
                    'content_title' => $content->meta->where('field_name', 'title')->first()->value ?? __('Untitled'),
                    'collection_name' => $content->collection->name ?? __('Unknown'),
                    'project_name' => $content->collection->project->name ?? __('Unknown'),
                    'project_id' => $content->project_id,
                    'collection_id' => $content->collection_id,
                    'user_name' => $content->creator->name ?? __('Unknown'),
                    'time' => $content->updated_at->diffForHumans(),
                    'timestamp' => $content->updated_at->timestamp,
                ];
            });

        return response()->json($activities);
    }

    /**
     * Get project IDs that the user has access to.
     * Mirrors Project::scopeForUser logic: owner_id OR project_user membership.
     */
    private function getUserAccessibleProjectIds($user)
    {
        // Super admin can access all projects
        if ($user->isSuperAdmin()) {
            return Project::pluck('id');
        }

        // Regular users: projects they own OR are members of
        return Project::where('owner_id', $user->id)
            ->orWhereHas('members', fn ($m) => $m->where('users.id', $user->id))
            ->pluck('id');
    }
}
