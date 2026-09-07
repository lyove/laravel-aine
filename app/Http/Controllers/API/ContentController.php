<?php

namespace App\Http\Controllers\API;

use App\Aine\ContentSerializer;
use App\Aine\HtmlSanitizer;
use App\Aine\PublicCache;
use App\Events\ContentCreated;
use App\Events\ContentPublished;
use App\Events\ContentTrashed;
use App\Events\ContentUnpublished;
use App\Events\ContentUpdated;
use App\Http\Controllers\API\Concerns\AuthorizesProjectApi;
use App\Http\Controllers\API\Concerns\HandlesBrowserCache;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContentResource;
use App\Models\Collection;
use App\Models\CollectionField;
use App\Models\Content;
use App\Models\ContentMeta;
use App\Models\Project;
use App\Services\Content\ContentMutationService;
use App\Services\Content\ContentQueryService;
use App\Services\Content\ContentValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ContentController extends Controller
{
    use AuthorizesProjectApi, HandlesBrowserCache;

    /** Public API response cache lifetime in seconds. */
    const PUBLIC_CACHE_TTL = 600;

    const MAX_PAGE_LIMIT  = 100;
    const MAX_PAGE_OFFSET = 10000;

    // -----------------------------------------------------------------
    // Service-layer dependencies
    // -----------------------------------------------------------------

    protected ContentQueryService      $queryService;
    protected ContentMutationService   $mutationService;
    protected ContentValidationService $validationService;

    public function __construct()
    {
        $this->validationService = new ContentValidationService();
        $this->mutationService   = new ContentMutationService($this->validationService);
        $this->queryService      = new ContentQueryService();
    }

    // =================================================================
    // Content list
    // =================================================================

    private function getContentListByUuid($uuid, $slug, Request $request)
    {
        $project = Project::where('uuid', $uuid)->first();
        if (! $project) return $this->notFound('Project not found');
        if ($response = $this->authorizeProjectRead($project)) return $response;

        $cacheKey = $this->publicCacheKey($project, 'list', $slug, $request, [$slug]);
        return $this->rememberPublicJson($cacheKey, function () use ($uuid, $slug, $request) {
            return $this->resolveContentListByUuid($uuid, $slug, $request);
        }, $project->public_api);
    }

    public function getContentList($project_identifier, $slug, Request $request)
    {
        $project = $request->attributes->get('resolved_project');
        if (! $project) return $this->notFound('Project not resolved');
        return $this->getContentListByUuid($project->uuid, $slug, $request);
    }

    // =================================================================
    // Content detail
    // =================================================================

    private function getContentByUuid($uuid, $slug, $slug_id, Request $request)
    {
        $project = Project::where('uuid', $uuid)->first();
        if (! $project) return response(['error' => 'Project not found!'], 404);
        if ($response = $this->authorizeProjectRead($project)) return $response;

        $cacheKey = $this->publicCacheKey($project, 'single', $slug . '/' . $slug_id, $request, [$slug]);
        return $this->rememberPublicJson($cacheKey, function () use ($uuid, $slug, $slug_id, $request) {
            return $this->resolveContentByUuid($uuid, $slug, $slug_id, $request);
        }, $project->public_api);
    }

    public function getProjectContentByID($project_identifier, $slug, $slug_id, Request $request)
    {
        $project = $request->attributes->get('resolved_project');
        if (! $project) return $this->notFound('Project not resolved');
        return $this->getContentByUuid($project->uuid, $slug, $slug_id, $request);
    }

    private function resolveContentByUuid($uuid, $slug, $slug_id, Request $request)
    {
        $project = Project::where('uuid', $uuid)->first();
        if (! $project) return response(['error' => 'Project not found!'], 404);
        if ($response = $this->authorizeProjectRead($project)) return $response;

        $collection = Collection::where('project_id', $project->id)->where('slug', $slug)->first();
        if (! $collection) return response(['error' => 'Collection not found!'], 404);

        $selectFields = ['id', 'project_id', 'collection_id', 'locale'];
        if ($request->has('timestamps')) {
            $selectFields = array_merge($selectFields, ['created_at', 'updated_at', 'published_at']);
        }

        $locale = $this->resolveLocale($request, $project);

        $content = Content::query()->with(['meta', 'collection.fields'])
            ->where('project_id', $project->id)
            ->where('collection_id', $collection->id)
            ->whereNotNull('published_at')
            ->whereNull('draft_parent_id')
            ->when($locale !== null, fn ($q) => $q->where('locale', $locale))
            ->select($selectFields)->find($slug_id);

        if (! $content) return $this->notFound('Not found');

        ContentSerializer::preload($content);
        return $this->success(new ContentResource($content), 'Success');
    }

    // =================================================================
    // Content by relation
    // =================================================================

    private function getContentByRelationByUuid($uuid, $slug, $slug_id, $relatedSlug, Request $request)
    {
        $project = $request->attributes->get('resolved_project')
            ?? Project::where('uuid', $uuid)->first();

        if (! $project) return $this->notFound('Project not found');
        if ($response = $this->authorizeProjectRead($project)) return $response;

        $cacheKey = $this->publicCacheKey($project, 'related', $slug . '/' . $slug_id . '/' . $relatedSlug, $request, [$slug, $relatedSlug]);
        return $this->rememberPublicJson($cacheKey, function () use ($project, $slug, $slug_id, $relatedSlug, $request) {
            return $this->resolveContentByRelation($project, $slug, $slug_id, $relatedSlug, $request);
        }, $project->public_api);
    }

    public function getProjectContentByRelation($project_identifier, $slug, $slug_id, $related_slug, Request $request)
    {
        $project = $request->attributes->get('resolved_project');
        if (! $project) return $this->notFound('Project not resolved');
        return $this->getContentByRelationByUuid($project->uuid, $slug, $slug_id, $related_slug, $request);
    }

    private function resolveContentByRelation(Project $project, $slug, $slug_id, $relatedSlug, Request $request)
    {
        $sourceCollection = Collection::where('project_id', $project->id)->where('slug', $slug)->first();
        if (! $sourceCollection) return $this->notFound('Source collection "' . $slug . '" not found in project');

        $relatedCollection = Collection::where('project_id', $project->id)->where('slug', $relatedSlug)->first();
        if (! $relatedCollection) return $this->notFound('Related collection "' . $relatedSlug . '" not found in project');

        $relationFields = CollectionField::where('project_id', $project->id)
            ->where('collection_id', $relatedCollection->id)->where('type', 'relation')->get();

        $relationField = null;
        foreach ($relationFields as $field) {
            $options = json_decode($field->options, true);
            if (isset($options['relation']['collection'])
                && ((string) $options['relation']['collection'] === (string) $sourceCollection->id)
            ) {
                $relationField = $field;
                break;
            }
        }

        if (! $relationField) {
            return $this->notFound('No relation field found from collection "' . $relatedSlug . '" to collection "' . $slug . '".');
        }

        $locale = $this->resolveLocale($request, $project);

        $content = Content::query()->with(['meta', 'collection.fields'])
            ->where('project_id', $project->id)->where('collection_id', $relatedCollection->id)
            ->whereNull('draft_parent_id')
            ->when($locale !== null, fn ($q) => $q->where('locale', $locale));

        $metaThroughRelation = ContentMeta::where('project_id', $project->id)
            ->where('collection_id', $relatedCollection->id)
            ->where('field_name', $relationField->name)
            ->where($this->relationValueMatcher($slug_id));

        $content->whereIn('id', $metaThroughRelation->get(['content_id']));

        // --- sort ---
        if ($request->has('sort')) {
            foreach (explode(',', $request->get('sort')) as $s) {
                $sort = explode(':', $s);
                if (count($sort) < 2) return $this->validationError('Incorrect sort statement');
                if (in_array($sort[0], ['id', 'locale', 'created_at', 'updated_at', 'published_at'])) {
                    $content->orderBy($sort[0], $sort[1]);
                } else {
                    $content->orderBy(
                        ContentMeta::select('value')->whereColumn('content_meta.content_id', 'content.id')
                            ->where('field_name', $sort[0])->latest()->take(1),
                        $sort[1]
                    );
                }
            }
        }

        // --- state filter ---
        if ($request->has('state')) {
            if ($request->get('state') === 'only_draft') $content->whereNull('published_at');
        } else {
            $content->whereNotNull('published_at');
        }

        if ($request->has('offset') && ! $request->has('limit')) {
            return $this->validationError('Incorrect offset statement.');
        }
        if ($paginationError = $this->validatePagination($request)) return $paginationError;

        if ($request->has('offset')) $content->offset(min((int) $request->get('offset'), self::MAX_PAGE_OFFSET));
        if ($request->has('limit'))  $content->limit(min((int) $request->get('limit'), self::MAX_PAGE_LIMIT));

        if ($request->has('count')) return $this->success($content->count(), 'Success');

        $selectFields = ['id', 'project_id', 'collection_id', 'locale'];
        if ($request->has('timestamps')) {
            $selectFields = array_merge($selectFields, ['created_at', 'updated_at', 'published_at']);
        }
        $content = $content->select($selectFields);

        if ($request->has('first')) {
            $content = $content->first();
            if (! $content) return $this->notFound('Not found');
            ContentSerializer::preload($content);
            return $this->success(new ContentResource($content), 'Success');
        }

        $content = $content->get();
        ContentSerializer::preload($content);
        return $this->success(ContentResource::collection($content), 'Success');
    }

    // =================================================================
    // Search
    // =================================================================

    public function searchContent($project_identifier, $slug, Request $request)
    {
        $project = $request->attributes->get('resolved_project');
        if (! $project) return $this->notFound('Project not resolved');
        return $this->searchContentByUuid($project->uuid, $slug, $request);
    }

    public function searchContentByUuid($uuid, $slug, Request $request)
    {
        $project = Project::where('uuid', $uuid)->first();
        if (! $project) return $this->notFound('Project not found');
        if ($response = $this->authorizeProjectRead($project)) return $response;

        $query = trim((string) $request->get('query', ''));
        $queryLen = mb_strlen($query);
        if ($queryLen < 2)   return $this->validationError('Search query must be at least 2 characters.');
        if ($queryLen > 100) return $this->validationError('Search query cannot exceed 100 characters.');

        $result = $this->queryService->search(
            $project, $slug, $query,
            (int) ($request->get('limit') ?: 20),
            (int) ($request->get('offset') ?: 0),
            $request->get('state'),
            $this->resolveLocale($request, $project)
        );

        $responseData = [];
        if ($result['tokens']->isNotEmpty()) {
            ContentSerializer::preload($result['tokens']);
            $responseData = json_decode(ContentResource::collection($result['tokens'])->toJson(), true);
        }

        return response()->json([
            'success' => true, 'code' => 200, 'message' => 'Success',
            'data' => $responseData, 'total' => $result['total'],
            'limit' => $result['limit'], 'offset' => $result['offset'],
        ], 200);
    }

    // =================================================================
    // Create content (API)
    // =================================================================

    public function createContent($project_identifier, $slug, Request $request)
    {
        $project = $request->attributes->get('resolved_project');
        if (! $project) return $this->notFound('Project not resolved');
        return $this->createContentByUuid($project->uuid, $slug, $request);
    }

    private function createContentByUuid($uuid, $slug, Request $request)
    {
        if ($response = $this->authorizeProjectAbility('create', $uuid)) return $response;

        $project = Project::where('uuid', $uuid)->first();
        if (! $project) return $this->notFound('Project not found');

        $collection = Collection::query()->with(['fields'])
            ->where('project_id', $project->id)->where('slug', $slug)->first();
        if (! $collection) return $this->notFound('Collection not found');

        $fields = $this->decodeFieldMeta($collection->fields);

        // Check repeatable fields are arrays.
        foreach ($fields as $field) {
            $opts = $field->options;
            if (! (isset($opts->repeatable) && $opts->repeatable)) continue;
            if (! $request->has($field->name)) continue;
            if (! is_array($request->get($field->name))) {
                return $this->validationError('Repeatable field ' . $field->name . ' must be an array!');
            }
        }

        // Build + run validation rules (API prefix: "" — no "data." wrapper).
        [$rules, $messages] = $this->validationService->buildFieldValidationRules($fields, '');
        ContentValidationService::registerCustomValidators();
        Validator::make($request->except(['locale']), $rules, $messages)->validate();

        // Unique validation.
        $input = $request->except(['locale', 'draft']);

        // Sanitize richtext fields (same as the admin path).
        $input = $this->sanitizeRichtextInput($input, $collection->fields);

        if ($uniqErrors = $this->validationService->validateUniqueFields($fields, $input, $collection->id)) {
            return response($uniqErrors, 422);
        }

        // Create via service.
        $content = $this->mutationService->create(
            $project, $collection, $input,
            $request->get('locale') ?: $project->default_locale,
            published: ! ($request->has('draft') && $request->get('draft') == 1),
            createdBy: null
        );

        ContentCreated::dispatch(['source' => 'API', 'content' => $content]);
        if ($content->isPublished()) {
            ContentPublished::dispatch(['source' => 'API', 'content' => $content]);
        }
        ContentSerializer::preload($content);
        return $this->created(new ContentResource($content), 'Content created successfully');
    }

    // =================================================================
    // Update content (API)
    // =================================================================

    public function updateContent($project_identifier, $slug, $slug_id, Request $request)
    {
        $project = $request->attributes->get('resolved_project');
        if (! $project) return $this->notFound('Project not resolved');
        return $this->updateContentByUuid($project->uuid, $slug, $slug_id, $request);
    }

    private function updateContentByUuid($uuid, $slug, $slug_id, Request $request)
    {
        if ($response = $this->authorizeProjectAbility('update', $uuid)) return $response;

        $project = Project::where('uuid', $uuid)->first();
        if (! $project) return $this->notFound('Project not found');

        $collection = Collection::query()->with(['fields'])
            ->where('project_id', $project->id)->where('slug', $slug)->first();
        if (! $collection) return $this->notFound('Collection not found');

        $content = Content::where('project_id', $project->id)
            ->where('collection_id', $collection->id)->where('id', $slug_id)->first();
        if (! $content) return $this->notFound('Record not found');

        $fields = $this->decodeFieldMeta($collection->fields);

        // Check repeatable fields.
        foreach ($fields as $field) {
            $opts = $field->options;
            if (! (isset($opts->repeatable) && $opts->repeatable)) continue;
            if (! $request->has($field->name)) continue;
            if (! is_array($request->get($field->name))) {
                return $this->validationError('Repeatable field ' . $field->name . ' must be an array!');
            }
        }

        [$rules, $messages] = $this->validationService->buildFieldValidationRules($fields, '');
        ContentValidationService::registerCustomValidators();
        Validator::make($request->all(), $rules, $messages)->validate();

        // Sanitize richtext fields.
        $input = $request->except(['locale', 'draft']);
        $input = $this->sanitizeRichtextInput($input, $collection->fields);

        // Unique check (excluding current content).
        if ($uniqErrors = $this->validationService->validateUniqueFields($fields, $input, $collection->id, $content->id)) {
            return response($uniqErrors, 422);
        }

        // Update via service.
        $this->mutationService->update(
            $content, $collection, $input,
            $request->get('locale'),
            published: ! ($request->has('draft') && $request->get('draft') == 1),
            updatedBy: auth('sanctum')->id(),
            scheduledAtRaw: $request->has('scheduled_at') ? $request->get('scheduled_at') : null,
            deletedMetaIds: $request->get('deleted', []),
        );

        ContentUpdated::dispatch(['source' => 'API', 'content' => $content]);
        $wasPublished = $content->getOriginal('published_at') !== null;
        if ($content->isPublished() && ! $wasPublished) {
            ContentPublished::dispatch(['source' => 'API', 'content' => $content]);
        } elseif (! $content->isPublished() && $wasPublished) {
            ContentUnpublished::dispatch(['source' => 'API', 'content' => $content]);
        }
        ContentSerializer::preload($content);
        return $this->updated(new ContentResource($content), 'Content updated successfully');
    }

    // =================================================================
    // Delete content (API)
    // =================================================================

    public function deleteContent($project_identifier, $slug, $slug_id, Request $request)
    {
        $project = $request->attributes->get('resolved_project');
        if (! $project) return $this->notFound('Project not resolved');
        return $this->deleteContentByUuid($project->uuid, $slug, $slug_id);
    }

    private function deleteContentByUuid($uuid, $slug, $slug_id)
    {
        if ($response = $this->authorizeProjectAbility('delete', $uuid)) return $response;

        $project = Project::where('uuid', $uuid)->first();
        if (! $project) return $this->notFound('Project not found');

        $collection = Collection::query()->with(['fields'])
            ->where('project_id', $project->id)->where('slug', $slug)->first();
        if (! $collection) return $this->notFound('Collection not found');

        $content = Content::where('project_id', $project->id)
            ->where('collection_id', $collection->id)->find($slug_id);
        if (! $content) return $this->notFound('Record not found');

        $this->mutationService->delete($content);
        ContentTrashed::dispatch(['source' => 'API', 'content' => $content]);
        return $this->deleted('Record deleted');
    }

    // =================================================================
    // Portal
    // =================================================================

    public function getPortalContent($project_identifier, Request $request)
    {
        $project = $request->attributes->get('resolved_project');
        if (! $project) return $this->notFound('Project not resolved');
        if ($response = $this->authorizeProjectRead($project)) return $response;

        $collectionSlug = $request->get('collection', 'articles');
        $cacheKey = $this->publicCacheKey($project, 'portal', $collectionSlug, $request, [$collectionSlug]);

        return $this->rememberPublicJson($cacheKey, function () use ($project, $collectionSlug, $request) {
            return $this->resolvePortalContent($project, $collectionSlug, $request);
        }, $project->public_api);
    }

    private function resolvePortalContent(Project $project, string $collectionSlug, Request $request): JsonResponse
    {
        $listFn = function (string $uuidOrId, string $slug, array $overrides,
                            ?string $relSlug = null, $relId = null) use ($project, $request) {
            $sub = $this->portalSubRequest($request, $overrides);
            if ($relSlug !== null && $relId !== null) {
                $resp = $this->resolveContentByRelation($project, $relSlug, $relId, $slug, $sub);
            } else {
                $resp = $this->resolveContentListByUuid($uuidOrId, $slug, $sub);
            }
            return $resp instanceof JsonResponse ? ($resp->getData(true)['data'] ?? []) : [];
        };

        // Categories
        $categories = $listFn($project->uuid, 'categories', []);
        $sections = [];
        foreach ($categories as $category) {
            $related = $listFn(
                $project->uuid, $collectionSlug,
                ['limit' => 50, 'sort' => 'published_at:desc', 'timestamps' => true, 'state' => 'only_published'],
                'categories', $category['id'] ?? null
            );
            $tagMap = [];
            foreach ($related as $item) {
                foreach ($item['tags'] ?? [] as $tag) $tagMap[$tag['id']] = $tag;
            }
            $sections[] = ['category' => $category, 'items' => array_slice($related, 0, 8), 'tags' => array_values($tagMap)];
        }

        $skel = fn (array $o) => ['sort' => 'published_at:desc', 'timestamps' => true] + $o;

        return response()->json([
            'success' => true, 'code' => 200, 'message' => 'Success',
            'data' => [
                'categories'  => $sections,
                'featured'    => $listFn($project->uuid, $collectionSlug, $skel(['filters.featured' => '1', 'limit' => 8])),
                'recommended' => $listFn($project->uuid, $collectionSlug, $skel(['filters.recommended' => '1', 'limit' => 8])),
                'slider'      => $listFn($project->uuid, $collectionSlug, $skel(['filters.slider' => '1', 'limit' => 5])),
                'latest'      => $listFn($project->uuid, $collectionSlug, $skel(['limit' => 10])),
                'pages'       => $listFn($project->uuid, 'pages', ['timestamps' => true]),
            ],
        ]);
    }

    private function portalSubRequest(Request $request, array $overrides): Request
    {
        $params = $request->query();
        foreach ($overrides as $key => $value) {
            if (is_string($key) && str_contains($key, '.') && ! str_contains($key, '[')) {
                $this->setNestedValue($params, $key, $value);
            } else {
                $params[$key] = $value;
            }
        }
        $sub = Request::create('/', 'GET', $params);
        $sub->attributes->set('resolved_project', $request->attributes->get('resolved_project'));
        return $sub;
    }

    /**
     * Set a value in a nested array using a dot-notation key.
     *
     * setNestedValue($arr, 'filters.featured', '1')
     *   => $arr['filters']['featured'] = '1'
     */
    private function setNestedValue(array &$array, string $key, $value): void
    {
        $segments = explode('.', $key);
        $current = &$array;
        foreach ($segments as $segment) {
            if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }
        $current = $value;
    }

    // =================================================================
    // Core query builder: content list by UUID (where / sort / paginate)
    // =================================================================

    private function resolveContentListByUuid($uuid, $slug, Request $request)
    {
        $project = Project::where('uuid', $uuid)->first();
        if (! $project) return $this->notFound('Project not found');
        if ($response = $this->authorizeProjectRead($project)) return $response;

        $collection = Collection::where('project_id', $project->id)->where('slug', $slug)->first();
        if (! $collection) return $this->notFound('Collection not found');

        $locale = $this->resolveLocale($request, $project);

        $content = Content::query()->with(['meta', 'collection.fields'])
            ->where('project_id', $project->id)->where('collection_id', $collection->id)
            ->whereNull('draft_parent_id')
            ->when($locale !== null, fn ($q) => $q->where('locale', $locale));

        // --- filters (dot-notation: filters.locale=zh, filters.title=contains.x) ---
        $this->parseFilters($request, $collection, $content, $locale);

        // --- sort ---
        if ($request->has('sort')) {
            foreach (explode(',', $request->get('sort')) as $s) {
                $sort = explode(':', $s);
                if (count($sort) < 2) return $this->validationError('Incorrect sort statement');
                if (in_array($sort[0], ['id', 'locale', 'created_at', 'updated_at', 'published_at'])) {
                    $content->orderBy($sort[0], $sort[1]);
                } else {
                    $content->orderBy(
                        ContentMeta::select('value')->whereColumn('content_meta.content_id', 'content.id')
                            ->where('field_name', $sort[0])->latest()->take(1),
                        $sort[1]
                    );
                }
            }
        }

        // --- state filter ---
        if ($request->has('state') && $request->get('state') === 'only_draft') {
            $content->whereNull('published_at');
        } else {
            $content->whereNotNull('published_at');
        }
        if ($request->has('offset') && ! $request->has('limit')) {
            return $this->validationError('Incorrect offset statement.');
        }
        if ($paginationError = $this->validatePagination($request)) return $paginationError;

        if ($request->has('offset')) $content->offset(min((int) $request->get('offset'), self::MAX_PAGE_OFFSET));
        if ($request->has('limit'))  $content->limit(min((int) $request->get('limit'), self::MAX_PAGE_LIMIT));

        if ($request->has('count')) return $this->success($content->count(), 'Success');

        $selectFields = ['id', 'project_id', 'collection_id', 'locale'];
        if ($request->has('timestamps')) {
            $selectFields = array_merge($selectFields, ['created_at', 'updated_at', 'published_at']);
        }
        $content->with(['meta', 'collection.fields'])->select($selectFields);

        if ($request->has('first')) {
            $content = $content->first();
            if (! $content) return $this->notFound('Not found');
            ContentSerializer::preload($content);
            return $this->success(new ContentResource($content), 'Success');
        }

        $content = $content->get();
        ContentSerializer::preload($content);
        return $this->success(ContentResource::collection($content), 'Success');
    }

    // =================================================================
    // Helpers
    // =================================================================

    /**
     * Resolve the language scope for public content queries.
     *
     * @return string|null  Resolved locale, or null when `all` was requested
     *                      (no language scope).
     */
    private function resolveLocale(Request $request, Project $project): ?string
    {
        $locale = $request->get('locale');
        if (is_string($locale) && $locale !== '') {
            return $locale === 'all' ? null : $locale;
        }

        $filters = $request->input('filters', []);
        if (is_array($filters) && isset($filters['locale'])
            && is_string($filters['locale']) && $filters['locale'] !== ''
            && ! str_contains($filters['locale'], '.')) {
            return $filters['locale'] === 'all' ? null : $filters['locale'];
        }

        return (string) ($project->default_locale ?: 'en');
    }

    private function relationValueMatcher($id): \Closure
    {
        return function ($query) use ($id) {
            $query->where('value', (string) $id)
                ->orWhere('value', 'like', (string) $id . ',%')
                ->orWhere('value', 'like', '%,' . (string) $id)
                ->orWhere('value', 'like', '%,' . (string) $id . ',%');
        };
    }

    /** Sanitize richtext field values before storage (mirrors admin preProcessData). */
    private function sanitizeRichtextInput(array $input, $fields): array
    {
        $richtextFields = [];
        foreach ($fields as $field) {
            if ($field->type === 'richtext') {
                $richtextFields[] = $field->name;
            }
        }
        foreach ($richtextFields as $name) {
            if (isset($input[$name]) && is_string($input[$name])) {
                $input[$name] = HtmlSanitizer::sanitize($input[$name]);
            }
        }
        return $input;
    }

    private function decodeFieldMeta($fields): array
    {
        $decoded = [];
        foreach ($fields as $field) {
            $f = clone $field;
            $f->validations = json_decode($f->validations);
            $f->options     = json_decode($f->options);
            $decoded[] = $f;
        }
        return $decoded;
    }

    // =================================================================
    // Caching helpers
    // =================================================================

    private function rememberPublicJson($cacheKey, callable $builder, bool $browserCacheable = false)
    {
        $etag = $this->publicApiEtag($cacheKey);
        $browserCache = $browserCacheable && ! auth('sanctum')->check();

        if ($browserCache && $this->ifNoneMatchMatches(request(), $etag)) {
            return $this->respondNotModified($etag);
        }

        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) {
            $response = response($cached['body'], $cached['status'])->header('Content-Type', 'application/json');
            if ($browserCache) {
                $response->header('ETag', $etag)->header('Cache-Control', 'no-cache, must-revalidate');
            }
            return $response;
        }

        $response = $builder();
        if ($response instanceof JsonResponse && $response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->cachePut($cacheKey, [
                'status' => $response->getStatusCode(),
                'body'   => $response->getContent(),
            ], self::PUBLIC_CACHE_TTL);
            if ($browserCache) {
                $response->header('ETag', $etag)->header('Cache-Control', 'no-cache, must-revalidate');
            }
        }
        return $response;
    }

    private function publicCacheKey($project, $endpoint, $slugPath, Request $request, array $collections = [])
    {
        $query = $request->query();
        $this->ksortRecursive($query);

        return implode(':', [
            'public_content', $this->cacheVersions($project->id, $collections), $project->id,
            $request->getSchemeAndHttpHost(), $endpoint, $slugPath,
            $this->resolveLocale($request, $project) ?? 'all',
            md5(json_encode($query)),
        ]);
    }

    /**
     * Version component of a public cache key: the project-wide version
     * always, plus the per-collection version of every collection the
     * response depends on. A project-level bump (locale management, …)
     * changes every key; a collection bump only changes keys that embed
     * that collection's version, so editing one collection no longer
     * invalidates the cached responses of the other collections.
     *
     * @param int $projectId
     * @param array $collections  Collection slugs the response depends on.
     * @return string
     */
    private function cacheVersions(int $projectId, array $collections): string
    {
        $parts = ['p'.PublicCache::version($projectId)];

        foreach (array_unique(array_filter($collections)) as $slug) {
            $parts[] = $slug.'@'.PublicCache::version($projectId, $slug);
        }

        return implode('|', $parts);
    }

    private function cacheGet($key, $default = null)
    {
        try { return Cache::get($key, $default); } catch (\Throwable $e) { return $default; }
    }

    private function cachePut($key, $value, $ttl)
    {
        try { Cache::put($key, $value, $ttl); } catch (\Throwable $e) { /* best-effort */ }
    }

    private function ksortRecursive(&$array)
    {
        if (! is_array($array)) return;
        ksort($array);
        foreach ($array as &$value) { if (is_array($value)) $this->ksortRecursive($value); }
        unset($value);
    }

    private function validatePagination(Request $request)
    {
        if ($request->has('limit')) {
            $limit = $request->get('limit');
            if (! is_numeric($limit) || (int) $limit < 1) {
                return $this->validationError('Invalid limit parameter.');
            }
        }
        if ($request->has('offset')) {
            $offset = $request->get('offset');
            if (! is_numeric($offset) || (int) $offset < 0) {
                return $this->validationError('Invalid offset parameter.');
            }
            if ((int) $offset > self::MAX_PAGE_OFFSET) {
                return $this->validationError('Offset cannot exceed ' . self::MAX_PAGE_OFFSET . '.');
            }
        }
        return null;
    }

    // =================================================================
    // Dot-notation filter engine (filters.locale=zh, filters.title=contains.x)
    // =================================================================

    /** Registered filter operators (must match the frontend serializeQuery list). */
    private const FILTER_OPERATORS = [
        'equals', 'notEquals', 'contains', 'notContains',
        'greaterThan', 'greaterThanOrEqual', 'lessThan', 'lessThanOrEqual',
        'in', 'notIn', 'between', 'notBetween', 'isEmpty', 'notEmpty',
    ];

    /** Direct columns on the content table (not stored in content_meta). */
    private const DIRECT_COLUMNS = ['id', 'locale', 'created_at', 'updated_at', 'published_at'];

    /**
     * Parse all `filters.*` query params and the `or` clause, applying them
     * to the given query builder.
     */
    private function parseFilters(Request $request, Collection $collection, $query, ?string $locale = null): void
    {
        $filters = $request->input('filters', []);
        if (! is_array($filters)) {
            $filters = [];
        }

        foreach ($this->flattenFilters($filters) as $fieldPath => $value) {
            $parsed = $this->parseFilterOperator((string) $value);
            $this->applyFieldFilter($query, $collection, $fieldPath, $parsed['operator'], $parsed['value'], $locale);
        }

        // OR clause: or=title.contains.vue,excerpt.contains.vue
        if ($request->has('or')) {
            $this->applyOrFilters($query, $collection, (string) $request->get('or'), $locale);
        }
    }

    /**
     * Flatten a nested filters array into dot-notation field paths.
     *
     *   ['locale' => 'zh', 'category' => ['slug' => 'tech']]
     *     => ['locale' => 'zh', 'category.slug' => 'tech']
     */
    private function flattenFilters(array $filters, string $prefix = ''): array
    {
        $result = [];
        foreach ($filters as $key => $value) {
            $fieldPath = $prefix === '' ? $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenFilters($value, $fieldPath));
            } else {
                $result[$fieldPath] = $value;
            }
        }
        return $result;
    }

    /**
     * Parse a raw value like "contains.laravel" into ['operator' => 'contains', 'value' => 'laravel'].
     * If the value has no dot, or the part before the dot is not a registered
     * operator, the whole value is treated as an equality match (so URLs and
     * filenames containing dots are safe).
     */
    private function parseFilterOperator(string $raw): array
    {
        $dotPos = strpos($raw, '.');
        if ($dotPos !== false) {
            $possibleOp = substr($raw, 0, $dotPos);
            if (in_array($possibleOp, self::FILTER_OPERATORS, true)) {
                return ['operator' => $possibleOp, 'value' => substr($raw, $dotPos + 1)];
            }
        }
        return ['operator' => 'equals', 'value' => $raw];
    }

    /**
     * Apply a single field filter.  Supports:
     *   - Direct columns (id, locale, created_at, ...) on the content table
     *   - Collection-defined meta fields via content_meta subquery
     *   - Relation filters via dotted path: category.url → category is a
     *     relation field, url is the target field in the related collection
     */
    private function applyFieldFilter($query, Collection $collection, string $field, string $operator, $value, ?string $locale = null): void
    {
        if ($field === 'locale' && (string) $value === 'all') {
            return;
        }

        $segments = explode('.', $field);
        if (count($segments) > 1) {
            $firstField = $segments[0];
            $fieldDef = $collection->fields->firstWhere('slug', $firstField)
                ?? $collection->fields->firstWhere('name', $firstField);

            if ($fieldDef && $fieldDef->type === 'relation') {
                $targetField = implode('.', array_slice($segments, 1));
                $this->applyRelationFilter($query, $collection, $firstField, $targetField, $operator, $value, $locale);
                return;
            }
        }

        // Direct column on the content table
        if (in_array($field, self::DIRECT_COLUMNS, true)) {
            $isDate = in_array($field, ['created_at', 'updated_at', 'published_at'], true);
            $this->applyOperatorToQuery($query, $field, $operator, $value, $isDate ? 'date' : 'string');
            return;
        }

        // Meta field
        $fieldDef = $collection->fields->firstWhere('slug', $field)
            ?? $collection->fields->firstWhere('name', $field);
        if (! $fieldDef) {
            return;
        }

        $metaQuery = ContentMeta::where('project_id', $collection->project_id)
            ->where('collection_id', $collection->id)
            ->where('field_name', $field);

        $this->applyOperatorToQuery($metaQuery, 'value', $operator, $value, $fieldDef->type ?? 'string');
        $query->whereIn('id', $metaQuery->select('content_id'));
    }

    /**
     * Apply an operator to a query builder column.  Used for both direct
     * columns and the meta `value` column.
     */
    private function applyOperatorToQuery($query, string $column, string $operator, $value, string $fieldType = 'string'): void
    {
        switch ($operator) {
            case 'equals':
                if ($fieldType === 'date') {
                    $query->whereDate($column, $value);
                } else {
                    $query->where($column, $value);
                }
                break;
            case 'notEquals':
                $query->where($column, '!=', $value);
                break;
            case 'contains':
                $query->where($column, 'LIKE', '%' . $value . '%');
                break;
            case 'notContains':
                $query->where($column, 'NOT LIKE', '%' . $value . '%');
                break;
            case 'greaterThan':
                $query->where($column, '>', $value);
                break;
            case 'greaterThanOrEqual':
                $query->where($column, '>=', $value);
                break;
            case 'lessThan':
                $query->where($column, '<', $value);
                break;
            case 'lessThanOrEqual':
                $query->where($column, '<=', $value);
                break;
            case 'in':
                $query->whereIn($column, array_map('trim', explode(',', $value)));
                break;
            case 'notIn':
                $query->whereNotIn($column, array_map('trim', explode(',', $value)));
                break;
            case 'between':
                $parts = array_map('trim', explode(',', $value));
                if (count($parts) === 2) {
                    $query->whereBetween($column, $parts);
                }
                break;
            case 'notBetween':
                $parts = array_map('trim', explode(',', $value));
                if (count($parts) === 2) {
                    $query->whereNotBetween($column, $parts);
                }
                break;
            case 'isEmpty':
                $query->where(function ($q) use ($column) {
                    $q->whereNull($column)->orWhere($column, '');
                });
                break;
            case 'notEmpty':
                $query->whereNotNull($column)->where($column, '!=', '');
                break;
        }
    }

    /**
     * Apply a relation filter: find records in the related collection matching
     * the target field condition, then match the main collection's relation
     * meta value against those IDs (supports comma-separated relation values).
     */
    private function applyRelationFilter($query, Collection $collection, string $relationField, string $targetField, string $operator, $value, ?string $locale = null): void
    {
        $fieldDef = $collection->fields->firstWhere('slug', $relationField)
            ?? $collection->fields->firstWhere('name', $relationField);
        if (! $fieldDef || $fieldDef->type !== 'relation') {
            return;
        }

        $options = json_decode($fieldDef->options);
        $relatedCollectionId = $options->relation->collection ?? null;
        if (! $relatedCollectionId) {
            return;
        }

        $relatedCollection = Collection::find($relatedCollectionId);
        if (! $relatedCollection) {
            return;
        }

        // Find matching IDs in the related collection
        $relatedQuery = Content::where('project_id', $collection->project_id)
            ->where('collection_id', $relatedCollection->id);

        if ($locale !== null && $locale !== '') {
            $relatedQuery->where('locale', $locale);
        }

        if (in_array($targetField, self::DIRECT_COLUMNS, true)) {
            $isDate = in_array($targetField, ['created_at', 'updated_at', 'published_at'], true);
            $this->applyOperatorToQuery($relatedQuery, $targetField, $operator, $value, $isDate ? 'date' : 'string');
        } else {
            $targetMeta = ContentMeta::where('project_id', $collection->project_id)
                ->where('collection_id', $relatedCollection->id)
                ->where('field_name', $targetField);
            $this->applyOperatorToQuery($targetMeta, 'value', $operator, $value, 'string');
            $relatedQuery->whereIn('id', $targetMeta->select('content_id'));
        }

        $relatedIds = $relatedQuery->pluck('id')->all();
        if (empty($relatedIds)) {
            $query->whereRaw('1=0');
            return;
        }

        // Match the main collection's relation meta against those IDs
        // (relation values may be comma-separated lists)
        $mainMeta = ContentMeta::where('project_id', $collection->project_id)
            ->where('collection_id', $collection->id)
            ->where('field_name', $relationField)
            ->where(function ($q) use ($relatedIds) {
                foreach ($relatedIds as $rid) {
                    $rid = (string) $rid;
                    $q->orWhere('value', $rid)
                      ->orWhere('value', 'like', $rid . ',%')
                      ->orWhere('value', 'like', '%,' . $rid)
                      ->orWhere('value', 'like', '%,' . $rid . ',%');
                }
            });

        $query->whereIn('id', $mainMeta->select('content_id'));
    }

    /**
     * Apply an OR clause.  The `or` param is a comma-separated list of
     * conditions, each parsed right-to-left:
     *   title.contains.vue          → field=title, operator=contains, value=vue
     *   category.url.contains.tech   → field=category.url, operator=contains, value=tech
     *   slug.my-article.html         → field=slug, operator=equals, value=my-article.html
     *   locale=zh                    → field=locale, operator=equals, value=zh
     *
     * Relation detection is handled by applyFieldFilter automatically.
     */
    private function applyOrFilters($query, Collection $collection, string $orClause, ?string $locale = null): void
    {
        $conditions = array_filter(array_map('trim', explode(',', $orClause)));
        if (empty($conditions)) {
            return;
        }

        $query->where(function ($outer) use ($collection, $conditions, $locale) {
            foreach ($conditions as $condition) {
                $segments = explode('.', $condition);
                if (count($segments) < 2) {
                    continue;
                }

                $value = end($segments);
                $operator = 'equals';
                $fieldSegments = array_slice($segments, 0, -1);

                if (count($segments) >= 3) {
                    $possibleOp = $segments[count($segments) - 2];
                    if (in_array($possibleOp, self::FILTER_OPERATORS, true)) {
                        $operator = $possibleOp;
                        $fieldSegments = array_slice($segments, 0, -2);
                    }
                }

                $field = implode('.', $fieldSegments);
                if ($field === '') {
                    continue;
                }

                $outer->orWhere(function ($subQuery) use ($collection, $field, $operator, $value, $locale) {
                    $this->applyFieldFilter($subQuery, $collection, $field, $operator, $value, $locale);
                });
            }
        });
    }
}