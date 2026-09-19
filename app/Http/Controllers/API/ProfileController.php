<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Content;
use App\Models\ContentMeta;
use App\Models\Favorite;
use App\Models\Like;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Frontend user profile: own information, avatar, and the user's
 * favorites / likes / comments across all projects.
 *
 * Users can only ever see and edit their own profile — the payload is
 * always derived from the authenticated user, never from a URL parameter
 * (prevents idor / privilege escalation; roles are not editable here).
 */
class ProfileController extends Controller
{
    private const MAX_ITEMS = 200;

    /** Own profile summary + interaction counts. */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->ok([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'email_verified_at' => $user->email_verified_at?->toDateTimeString(),
            'created_at' => $user->created_at?->toDateTimeString(),
            'favorites_count' => Favorite::where('user_id', $user->id)->count(),
            'likes_count' => Like::where('user_id', $user->id)->count(),
            'comments_count' => $this->myCommentsQuery($user->id)->count(),
        ]);
    }

    /** Update the user's password. */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!\Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
            return $this->fail('Current password is incorrect.', 422);
        }

        $user->password = \Illuminate\Support\Facades\Hash::make($request->password);
        $user->save();

        return $this->ok(['message' => 'Password updated.']);
    }

    /** Upload / replace the user's avatar. */
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp,gif', 'max:2048'],
        ], [
            'avatar.required' => 'Please choose an avatar image.',
            'avatar.uploaded' => 'The avatar file is too large. Please choose an image under 2 MB.',
            'avatar.image' => 'The avatar must be a valid image file.',
            'avatar.mimes' => 'The avatar must be a JPEG, PNG, WebP or GIF image.',
            'avatar.max' => 'The avatar image must not exceed 2 MB.',
        ]);

        $user = $request->user();
        $file = $request->file('avatar');
        $disk = Storage::disk('local');

        // Remove any previous avatar for this user to avoid orphan files.
        foreach ($disk->files('public/avatars') as $existing) {
            if (str_starts_with(basename($existing), "user_{$user->id}.")) {
                $disk->delete($existing);
            }
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'png'));
        $file->storeAs('public/avatars', "user_{$user->id}.{$ext}", 'local');

        $user->avatar = '/storage/avatars/user_'.$user->id.'.'.$ext;
        $user->save();

        return $this->ok(['avatar' => $user->avatar]);
    }

    /** The user's favorites across all projects, newest first. */
    public function favorites(Request $request): JsonResponse
    {
        $rows = Favorite::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(self::MAX_ITEMS)
            ->get();

        return $this->ok($this->enrichInteractionRows($rows, 'favorited_at'));
    }

    /** The user's likes across all projects, newest first. */
    public function likes(Request $request): JsonResponse
    {
        $rows = Like::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(self::MAX_ITEMS)
            ->get();

        return $this->ok($this->enrichInteractionRows($rows, 'liked_at'));
    }

    /** The user's submitted comments across all projects, newest first. */
    public function comments(Request $request): JsonResponse
    {
        $user = $request->user();
        $comments = $this->myCommentsQuery($user->id)
            ->orderByDesc('created_at')
            ->limit(self::MAX_ITEMS)
            ->get()
            ->map(fn (Content $comment) => $this->presentComment($comment))
            ->values()
            ->all();

        return $this->ok($comments);
    }

    // -----------------------------------------------------------------
    // Enrichment helpers
    // -----------------------------------------------------------------

    /**
     * Turn favorite/like rows into display items: content title/slug,
     * category slug, project label + identifier, collection slug.
     *
     * @param  BaseCollection<int, Favorite|Like>  $rows
     */
    private function enrichInteractionRows(BaseCollection $rows, string $interactionField): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $contentIds = $rows->pluck('content_id')->unique()->values();
        $projectIds = $rows->pluck('project_id')->unique()->values();

        $contents = Content::withTrashed()
            ->whereIn('id', $contentIds)
            ->get(['id', 'collection_id', 'published_at'])
            ->keyBy('id');

        $meta = $this->metaLookup($contentIds, ['title', 'slug', 'category']);
        $itemMeta = $this->itemMeta($meta, $contents);

        $projects = Project::whereIn('id', $projectIds)->get(['id', 'slug', 'name'])->keyBy('id');
        $collectionIds = $contents->pluck('collection_id')->filter()->unique()->values();
        $collections = Collection::whereIn('id', $collectionIds)->get(['id', 'slug'])->keyBy('id');

        return $rows->map(function ($row) use ($itemMeta, $projects, $collections, $interactionField) {
            $meta = $itemMeta[$row->content_id] ?? [];
            $project = $projects[$row->project_id] ?? null;
            $content = $meta['content'] ?? null;
            $collection = $content ? ($collections[$content->collection_id]->slug ?? null) : null;

            return [
                'content_id' => $row->content_id,
                'title' => $meta['title'] ?? null,
                'slug' => $meta['slug'] ?? null,
                'category_slug' => $meta['category_slug'] ?? null,
                'collection' => $collection,
                'project_identifier' => $project?->slug,
                'project_name' => $project?->name,
                'published' => $content ? ($content->published_at !== null) : false,
                $interactionField => $row->created_at?->toDateTimeString(),
            ];
        })->values()->all();
    }

    /**
     * Per-content display meta: title, slug, category slug, collection slug,
     * and the underlying content row (for published state).
     *
     * @param  array<int, array<string, string>>  $meta   field_name => value per content id
     * @param  \Illuminate\Support\Collection<int, Content>  $contents
     */
    private function itemMeta(array $meta, $contents): array
    {
        // Resolve category content ids → their slug/title (one batch).
        $categoryIds = collect($meta)
            ->pluck('category')
            ->filter()
            ->unique()
            ->values();
        $categoryMeta = $categoryIds->isNotEmpty()
            ? $this->metaLookup($categoryIds, ['slug', 'title'])
            : [];

        $result = [];
        foreach ($meta as $contentId => $fields) {
            $content = $contents->get($contentId);
            $categorySlug = null;
            if (! empty($fields['category'])) {
                $categorySlug = $categoryMeta[(int) $fields['category']]['slug'] ?? null;
            }

            $result[$contentId] = [
                'title' => $fields['title'] ?? null,
                'slug' => $fields['slug'] ?? null,
                'category_slug' => $categorySlug,
                'content' => $content,
            ];
        }

        return $result;
    }

    /**
     * Batched ContentMeta lookup: returns per-content-id arrays of
     * field_name => value for the requested fields.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $contentIds
     * @param  array<int, string>  $fields
     * @return array<int, array<string, string>>
     */
    private function metaLookup($contentIds, array $fields): array
    {
        $rows = ContentMeta::whereIn('content_id', $contentIds)
            ->whereIn('field_name', $fields)
            ->get(['content_id', 'field_name', 'value']);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row->content_id][$row->field_name] = $row->value;
        }

        return $grouped;
    }

    /** Present one comment row with the linked article's display info. */
    private function presentComment(Content $comment): array
    {
        $meta = $comment->meta->pluck('value', 'field_name');
        $articleId = isset($meta['article']) ? (int) $meta['article'] : null;
        $project = Project::find($comment->project_id);

        $articleInfo = $articleId ? $this->articleInfo($articleId) : null;

        return [
            'id' => $comment->id,
            'comment' => $meta['comment'] ?? null,
            'status' => $meta['status'] ?? 'pending',
            'article_id' => $articleId,
            'article_title' => $articleInfo['title'] ?? null,
            'article_slug' => $articleInfo['slug'] ?? null,
            'article_category_slug' => $articleInfo['category_slug'] ?? null,
            'project_identifier' => $project?->slug,
            'project_name' => $project?->name,
            'created_at' => $comment->created_at?->toDateTimeString(),
        ];
    }

    /** Title / slug / category slug for one article (for comment links). */
    private function articleInfo(int $articleId): array
    {
        $meta = $this->metaLookup(collect([$articleId]), ['title', 'slug', 'category']);
        $fields = $meta[$articleId] ?? [];
        $categorySlug = null;
        if (! empty($fields['category'])) {
            $categorySlug = $this->metaLookup(
                collect([(int) $fields['category']]),
                ['slug']
            )[(int) $fields['category']]['slug'] ?? null;
        }

        return [
            'title' => $fields['title'] ?? null,
            'slug' => $fields['slug'] ?? null,
            'category_slug' => $categorySlug,
        ];
    }

    private function myCommentsQuery(int $userId)
    {
        $commentCollectionIds = Collection::where('slug', 'comments')->pluck('id');

        return Content::withTrashed()
            ->with('meta')
            ->where('created_by', $userId)
            ->whereIn('collection_id', $commentCollectionIds);
    }

    private function ok(array $data): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => null,
            'data' => $data,
        ]);
    }
}
