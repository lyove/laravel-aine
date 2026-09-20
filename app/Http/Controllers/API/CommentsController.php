<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Content;
use App\Models\ContentMeta;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Content comments for the public API.
 */
class CommentsController extends Controller
{
    private const MODERATION_AUTO = 'auto';
    private const MODERATION_APPROVAL = 'approval';
    private const MODERATION_DISABLED = 'disabled';

    /** List approved comments for one article, oldest first. */
    public function index(Request $request, string $projectIdentifier, int $articleId): JsonResponse
    {
        $project = $this->resolveProject($request);

        $article = $this->findArticle($project, $articleId);
        if (! $article) {
            return $this->apiError(404, __('Article not found'));
        }

        $collection = $this->commentsCollection($project);
        if (! $collection) {
            return $this->apiError(404, __('Comments are not enabled for this project'));
        }

        $query = Content::query()
            ->with(['meta'])
            ->where('project_id', $project->id)
            ->where('collection_id', $collection->id)
            ->whereNull('draft_parent_id')
            ->whereHas('meta', fn ($q) => $q->where('field_name', 'status')->where('value', 'approved'))
            ->whereHas('meta', fn ($q) => $q->where('field_name', 'article')->where('value', (string) $article->id))
            ->orderBy('created_at');

        $limit = (int) $request->query('limit', 0);
        if ($limit > 0) {
            $query->limit(min($limit, 100))->offset(max(0, (int) $request->query('offset', 0)));
        }

        $comments = $query->get();

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => null,
            'data' => $comments->map(fn (Content $comment) => $this->present($comment))->all(),
        ]);
    }

    /**
     * Submit a comment as the logged-in platform user.
     * Whether it appears immediately depends on the article's
     * `comments_moderation` setting (auto / approval / disabled).
     */
    public function store(Request $request): JsonResponse
    {
        $project = $this->resolveProject($request);

        $validated = $request->validate([
            'article_id' => ['required', 'integer'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $article = $this->findArticle($project, (int) $validated['article_id']);
        if (! $article) {
            return $this->apiError(404, __('Article not found'));
        }

        $collection = $this->commentsCollection($project);
        if (! $collection) {
            return $this->apiError(404, __('Comments are not enabled for this project'));
        }

        $mode = $this->moderationMode($article);

        if ($mode === self::MODERATION_DISABLED) {
            return $this->apiError(422, __('Comments are disabled for this article'));
        }

        $user = $request->user();
        $autoApproved = $mode === self::MODERATION_AUTO;

        $comment = Content::create([
            'project_id' => $project->id,
            'collection_id' => $collection->id,
            'locale' => $article->locale ?? 'en',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        foreach ([
            'name' => $user->name,
            'e-mail' => $user->email,
            'comment' => (string) $validated['comment'],
            'article' => (string) $article->id,
            'status' => $autoApproved ? 'approved' : 'pending',
        ] as $field => $value) {
            ContentMeta::create([
                'project_id' => $project->id,
                'collection_id' => $collection->id,
                'content_id' => $comment->id,
                'field_name' => $field,
                'value' => (string) $value,
            ]);
        }

        return response()->json([
            'success' => true,
            'code' => 201,
            'message' => $autoApproved ? null : __('Comment submitted and awaiting moderation'),
            'data' => [
                'id' => $comment->id,
                'status' => $autoApproved ? 'approved' : 'pending',
            ],
        ], 201);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function resolveProject(Request $request): ?Project
    {
        return $request->attributes->get('resolved_project');
    }

    private function findArticle(Project $project, int $articleId): ?Content
    {
        $articles = Collection::where('project_id', $project->id)->where('slug', 'articles')->first();

        return $articles
            ? Content::where('project_id', $project->id)
                ->where('collection_id', $articles->id)
                ->where('id', $articleId)
                ->whereNotNull('published_at')
                ->first()
            : null;
    }

    private function commentsCollection(Project $project): ?Collection
    {
        return Collection::where('project_id', $project->id)->where('slug', 'comments')->first();
    }

    /** Article's comments_moderation value (auto when unset). */
    private function moderationMode(Content $article): string
    {
        $value = ContentMeta::where('content_id', $article->id)
            ->where('field_name', 'comments_moderation')
            ->value('value');

        return in_array($value, [self::MODERATION_AUTO, self::MODERATION_APPROVAL, self::MODERATION_DISABLED], true)
            ? $value
            : self::MODERATION_AUTO;
    }

    private function metaValue(Content $comment, string $field): ?string
    {
        return $comment->meta->firstWhere('field_name', $field)?->value;
    }

    private function present(Content $comment): array
    {
        return [
            'id' => $comment->id,
            'name' => $this->metaValue($comment, 'name'),
            'comment' => $this->metaValue($comment, 'comment'),
            'article_id' => (int) $this->metaValue($comment, 'article'),
            'status' => $this->metaValue($comment, 'status') ?? 'pending',
            'created_at' => $comment->created_at?->toDateTimeString(),
        ];
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
