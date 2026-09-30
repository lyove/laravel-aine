<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Favorite;
use App\Models\Like;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Favorites & likes for the public frontend.
 */
class InteractionsController extends Controller
{
    /** Add a favorite (idempotent). */
    public function addFavorite(Request $request, string $projectIdentifier): JsonResponse
    {
        $project = $this->resolveProject($request);
        $content = $this->findPublished($project, (int) $request->input('content_id'));
        if (! $content) {
            return $this->apiError(404, __('Content not found'));
        }

        Favorite::firstOrCreate([
            'user_id' => $request->user()->id,
            'project_id' => $project->id,
            'content_id' => $content->id,
        ]);

        return $this->stateResponse($request, $project, $content, true);
    }

    /** Remove a favorite (idempotent). */
    public function removeFavorite(Request $request, string $projectIdentifier, int $contentId): JsonResponse
    {
        $project = $this->resolveProject($request);

        Favorite::where('user_id', $request->user()->id)
            ->where('project_id', $project->id)
            ->where('content_id', $contentId)
            ->delete();

        $content = Content::where('project_id', $project->id)->where('id', $contentId)->first();

        return $this->stateResponse($request, $project, $content, true);
    }

    /** Add a like (idempotent). */
    public function addLike(Request $request, string $projectIdentifier): JsonResponse
    {
        $project = $this->resolveProject($request);
        $content = $this->findPublished($project, (int) $request->input('content_id'));
        if (! $content) {
            return $this->apiError(404, __('Content not found'));
        }

        Like::firstOrCreate([
            'user_id' => $request->user()->id,
            'project_id' => $project->id,
            'content_id' => $content->id,
        ]);

        return $this->stateResponse($request, $project, $content, true);
    }

    /** Remove a like (idempotent). */
    public function removeLike(Request $request, string $projectIdentifier, int $contentId): JsonResponse
    {
        $project = $this->resolveProject($request);

        Like::where('user_id', $request->user()->id)
            ->where('project_id', $project->id)
            ->where('content_id', $contentId)
            ->delete();

        $content = Content::where('project_id', $project->id)->where('id', $contentId)->first();

        return $this->stateResponse($request, $project, $content, true);
    }

    /** Current counts + the authenticated user's state for one content row. */
    public function state(Request $request, string $projectIdentifier, int $contentId): JsonResponse
    {
        $project = $this->resolveProject($request);
        $content = $this->findPublished($project, $contentId);

        if (! $content) {
            return $this->apiError(404, __('Content not found'));
        }

        return $this->stateResponse($request, $project, $content, true);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Resolve the acting user: session (web SPA) or Sanctum bearer token.
     * The state route is anonymous so guests can read counts; this stays optional.
     */
    private function stateUser(Request $request)
    {
        if ($request->user()) {
            return $request->user();
        }
        $token = $request->bearerToken();
        if ($token) {
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($accessToken) {
                return $accessToken->tokenable;
            }
        }
        return null;
    }

    private function resolveProject(Request $request): ?Project
    {
        return $request->attributes->get('resolved_project');
    }

    private function findPublished(Project $project, int $contentId): ?Content
    {
        return Content::where('project_id', $project->id)
            ->where('id', $contentId)
            ->whereNotNull('published_at')
            ->whereNull('draft_parent_id')
            ->first();
    }

    private function stateResponse(Request $request, Project $project, ?Content $content, bool $withUserState): JsonResponse
    {
        if (! $content) {
            return $this->apiError(404, __('Content not found'));
        }

        $data = [
            'content_id' => $content->id,
            'favorite_count' => Favorite::where('project_id', $project->id)->where('content_id', $content->id)->count(),
            'like_count' => Like::where('project_id', $project->id)->where('content_id', $content->id)->count(),
        ];

        $user = $this->stateUser($request);
        if ($withUserState && $user) {
            $data['is_favorited'] = Favorite::where('user_id', $user->id)
                ->where('project_id', $project->id)->where('content_id', $content->id)->exists();
            $data['is_liked'] = Like::where('user_id', $user->id)
                ->where('project_id', $project->id)->where('content_id', $content->id)->exists();
        } else {
            $data['is_favorited'] = false;
            $data['is_liked'] = false;
        }

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => null,
            'data' => $data,
        ]);
    }

    private function apiError(int $code, string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'data' => null,
        ], $code);
    }
}
