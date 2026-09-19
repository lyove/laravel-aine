<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Content;
use App\Models\ContentMeta;
use App\Models\Project;
use Illuminate\Http\Request;

/**
 * Comment moderation for the admin area.
 *
 * Comment status is an explicit field (pending / approved / spam / trash),
 * aligned with mainstream blog systems (e.g. WordPress). The public API
 * only exposes comments whose status is `approved`.
 *
 * Only users with manageContent (owner/admin/editor) can moderate.
 */
class CommentsController extends Controller
{
    /** List comments for a project, grouped-ready rows with article context. */
    public function index(Request $request, int $projectId)
    {
        $project = Project::findOrFail($projectId);
        $this->authorize('manageContent', $project);

        $commentsCollection = Collection::where('project_id', $project->id)->where('slug', 'comments')->first();
        if (! $commentsCollection) {
            return response(['success' => false, 'message' => __('Comments are not enabled for this project')], 404);
        }

        $filter = $request->get('filter', 'pending'); // pending | approved | spam | trash | all

        $comments = Content::with(['meta'])
            ->where('project_id', $project->id)
            ->where('collection_id', $commentsCollection->id)
            ->when(in_array($filter, ['pending', 'approved', 'spam', 'trash'], true), fn ($q) => $q->whereHas(
                'meta',
                fn ($m) => $m->where('field_name', 'status')->where('value', $filter)
            ))
            ->orderByDesc('created_at')
            ->get();

        $articleTitles = $this->articleTitles($project, $comments);

        $rows = $comments->map(function (Content $comment) use ($project, $articleTitles) {
            $meta = $comment->meta->pluck('value', 'field_name');
            $articleId = isset($meta['article']) ? (int) $meta['article'] : null;

            return [
                'id' => $comment->id,
                'name' => $meta['name'] ?? null,
                'email' => $meta['e-mail'] ?? null,
                'comment' => $meta['comment'] ?? null,
                'article_id' => $articleId,
                'article_title' => $articleId !== null ? ($articleTitles[$articleId] ?? 'Deleted article') : null,
                'status' => $meta['status'] ?? 'pending',
                'created_at' => $comment->created_at?->toDateTimeString(),
            ];
        })->values();

        $counts = [
            'pending' => $this->countStatus($project, $commentsCollection->id, 'pending'),
            'approved' => $this->countStatus($project, $commentsCollection->id, 'approved'),
            'spam' => $this->countStatus($project, $commentsCollection->id, 'spam'),
            'trash' => $this->countStatus($project, $commentsCollection->id, 'trash'),
        ];

        return response(['rows' => $rows, 'counts' => $counts], 200);
    }

    /** Approve a pending comment so it becomes visible on the blog. */
    public function approve(int $projectId, int $contentId)
    {
        return $this->transition($projectId, $contentId, 'approved', 'Comment approved');
    }

    /** Mark a comment as spam (removed from the queue, recoverable). */
    public function spam(int $projectId, int $contentId)
    {
        return $this->transition($projectId, $contentId, 'spam', 'Comment marked as spam');
    }

    /** Move a comment to trash (removed from the queue, recoverable). */
    public function reject(int $projectId, int $contentId)
    {
        return $this->transition($projectId, $contentId, 'trash', 'Comment moved to trash');
    }

    /** Restore a trashed or spam comment back to pending. */
    public function restore(int $projectId, int $contentId)
    {
        return $this->transition($projectId, $contentId, 'pending', 'Comment restored');
    }

    /** Bulk moderation: action = approve | spam | trash | restore, ids = [..]. */
    public function bulk(int $projectId, Request $request)
    {
        $project = Project::findOrFail($projectId);
        $this->authorize('manageContent', $project);

        $action = $request->get('action');
        if (! in_array($action, ['approve', 'spam', 'trash', 'restore', 'delete'], true)) {
            return response(['success' => false, 'message' => __('Invalid action')], 422);
        }

        $commentsCollection = Collection::where('project_id', $project->id)->where('slug', 'comments')->first();
        if (! $commentsCollection) {
            return response(['success' => false, 'message' => __('Comments are not enabled for this project')], 404);
        }

        $ids = array_filter(array_map('intval', (array) $request->get('ids', [])));
        if ($ids === []) {
            return response(['success' => false, 'message' => __('No comments selected')], 422);
        }

        if ($action === 'delete') {
            $deleted = Content::where('project_id', $project->id)
                ->where('collection_id', $commentsCollection->id)
                ->whereIn('id', $ids)
                ->forceDelete();

            return response(['success' => true, 'message' => __('Comments deleted'), 'updated' => $deleted], 200);
        }

        $target = match ($action) {
            'approve' => 'approved',
            'spam' => 'spam',
            'trash' => 'trash',
            'restore' => 'pending',
        };

        $comments = Content::where('project_id', $project->id)
            ->where('collection_id', $commentsCollection->id)
            ->whereIn('id', $ids)
            ->get();

        foreach ($comments as $comment) {
            $this->setStatus($project, $comment, $target);
        }

        return response(['success' => true, 'message' => __('Comments updated'), 'updated' => $comments->count()], 200);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function transition(int $projectId, int $contentId, string $target, string $message)
    {
        $project = Project::findOrFail($projectId);
        $this->authorize('manageContent', $project);

        $comment = Content::where('project_id', $project->id)->where('id', $contentId)->firstOrFail();
        $this->setStatus($project, $comment, $target);

        return response([
            'success' => true,
            'message' => $message,
            'data' => ['id' => $comment->id, 'status' => $target],
        ], 200);
    }

    private function setStatus(Project $project, Content $comment, string $status): void
    {
        ContentMeta::updateOrCreate(
            [
                'project_id' => $project->id,
                'collection_id' => $comment->collection_id,
                'content_id' => $comment->id,
                'field_name' => 'status',
            ],
            ['value' => $status]
        );
    }

    private function countStatus(Project $project, int $collectionId, string $status): int
    {
        return Content::where('project_id', $project->id)
            ->where('collection_id', $collectionId)
            ->whereHas('meta', fn ($q) => $q->where('field_name', 'status')->where('value', $status))
            ->count();
    }

    private function articleTitles(Project $project, $comments): array
    {
        $articleIds = $comments
            ->map(fn (Content $c) => (int) ($c->meta->firstWhere('field_name', 'article')?->value ?? 0))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($articleIds->isEmpty()) {
            return [];
        }

        return ContentMeta::where('project_id', $project->id)
            ->whereIn('content_id', $articleIds)
            ->where('field_name', 'title')
            ->pluck('value', 'content_id')
            ->all();
    }
}
