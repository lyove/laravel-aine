<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\CollectionField;
use App\Models\Content;
use App\Models\ContentMeta;
use App\Models\Media;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Single-content endpoints
 */
class ContentIdentifierTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;
    private Collection $articles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::create([
            'name' => 'Blog',
            'slug' => 'blog',
            'status' => 1,
            'public_api' => 1,
            'default_locale' => 'en',
        ]);

        $this->articles = Collection::create([
            'name' => 'Articles', 'slug' => 'articles',
            'project_id' => $this->project->id, 'order' => 1,
        ]);
        $this->addField($this->articles, 'title', 'text', ['required' => true]);
        $this->addField($this->articles, 'slug', 'slug', ['required' => true, 'unique' => true]);
    }

    private function addField(Collection $collection, string $name, string $type, array $flags = []): void
    {
        CollectionField::create([
            'type' => $type,
            'label' => ucfirst($name),
            'name' => $name,
            'options' => json_encode([
                'slug' => [], 'media' => [], 'relation' => [], 'enumeration' => [],
                'hideInContentList' => false,
            ]),
            'validations' => json_encode([
                'required' => ['status' => $flags['required'] ?? false, 'message' => null],
                'charcount' => ['status' => false, 'type' => '', 'min' => null, 'max' => null],
                'unique' => ['status' => $flags['unique'] ?? false, 'message' => null],
            ]),
            'project_id' => $this->project->id,
            'collection_id' => $collection->id,
            'order' => 1,
        ]);
    }

    private function addContent(int $collectionId, string $locale, array $fields, $publishedAt = null, ?int $explicitId = null): Content
    {
        $content = new Content([
            'project_id' => $this->project->id,
            'collection_id' => $collectionId,
            'locale' => $locale,
            'published_at' => $publishedAt,
        ]);
        if ($explicitId !== null) {
            $content->id = $explicitId;
        }
        $content->save();

        foreach ($fields as $name => $value) {
            ContentMeta::create([
                'project_id' => $this->project->id,
                'collection_id' => $collectionId,
                'content_id' => $content->id,
                'field_name' => $name,
                'value' => $value,
            ]);
        }

        return $content;
    }

    // =================================================================
    // ID endpoint: GET /{collection}/{id}
    // =================================================================

    public function test_by_id_returns_the_article_as_a_single_object(): void
    {
        $article = $this->addContent($this->articles->id, 'en', [
            'title' => 'Hello World', 'slug' => 'hello-world',
        ], now());

        $response = $this->getJson('/api/project/blog/articles/' . $article->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $article->id)
            ->assertJsonPath('data.title', 'Hello World');
    }

    public function test_by_unknown_id_returns_404(): void
    {
        $this->getJson('/api/project/blog/articles/999999999')
            ->assertStatus(404);
    }

    // =================================================================
    // Slug endpoint: GET /{collection}/slug/{slug}
    // =================================================================

    public function test_by_slug_returns_the_article_as_a_single_object(): void
    {
        $article = $this->addContent($this->articles->id, 'en', [
            'title' => 'Security Guide', 'slug' => 'security-best-practices-headless-cms-zh',
        ], now());

        $response = $this->getJson('/api/project/blog/articles/slug/security-best-practices-headless-cms-zh');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $article->id)
            ->assertJsonPath('data.title', 'Security Guide');

        // The response must be a single object, not an array.
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
    }

    public function test_by_slug_respects_locale_scoping(): void
    {
        $en = $this->addContent($this->articles->id, 'en', [
            'title' => 'Hello EN', 'slug' => 'hello',
        ], now());
        $zh = $this->addContent($this->articles->id, 'zh', [
            'title' => '你好 ZH', 'slug' => 'hello',
        ], now());

        // Default locale (en) is used when no locale is given.
        $this->getJson('/api/project/blog/articles/slug/hello')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $en->id);

        // Explicit locale scopes the slug lookup.
        $this->getJson('/api/project/blog/articles/slug/hello?locale=zh')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $zh->id);

        $this->getJson('/api/project/blog/articles/slug/hello?locale=en')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $en->id);
    }

    public function test_by_slug_uses_only_the_slug_field_not_other_meta(): void
    {
        $this->addContent($this->articles->id, 'en', [
            'title' => 'hello', 'slug' => 'real-slug',
        ], now());

        // "hello" exists as a title, but not as a slug -> 404.
        $this->getJson('/api/project/blog/articles/slug/hello')
            ->assertStatus(404);

        $this->getJson('/api/project/blog/articles/slug/real-slug')
            ->assertStatus(200);
    }

    public function test_by_unknown_slug_returns_404(): void
    {
        $this->getJson('/api/project/blog/articles/slug/does-not-exist')
            ->assertStatus(404);
    }

    public function test_draft_is_not_found_by_slug(): void
    {
        $this->addContent($this->articles->id, 'en', [
            'title' => 'Draft', 'slug' => 'secret-draft',
        ], null);

        $this->getJson('/api/project/blog/articles/slug/secret-draft')
            ->assertStatus(404);
    }

    public function test_uuid_group_also_resolves_by_slug(): void
    {
        // The UUID group is backend-to-backend and requires a Sanctum token
        // on every route (see routes/api.php UUID group middleware).
        $token = $this->project->createToken('test-token', ['read']);
        $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken);

        $article = $this->addContent($this->articles->id, 'en', [
            'title' => 'By UUID', 'slug' => 'uuid-slug',
        ], now());

        $this->getJson('/api/' . $this->project->uuid . '/articles/slug/uuid-slug')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $article->id);
    }

    // =================================================================
    // ID and slug endpoints are independent — no heuristic, no fallback
    // =================================================================

    public function test_numeric_identifier_does_not_fall_back_to_slug(): void
    {
        $numericSlug = $this->addContent($this->articles->id, 'en', [
            'title' => 'Numeric Slug', 'slug' => '9001',
        ], now());

        // No record has ID 9001: the ID endpoint must 404 (no slug fallback).
        $this->getJson('/api/project/blog/articles/9001')
            ->assertStatus(404);

        // The same value resolves through the explicit slug endpoint.
        $this->getJson('/api/project/blog/articles/slug/9001')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $numericSlug->id);
    }

    public function test_id_endpoint_and_slug_endpoint_never_intersect(): void
    {
        $byId = $this->addContent($this->articles->id, 'en', [
            'title' => 'ID Record', 'slug' => 'id-record',
        ], now(), 7001);

        $numericSlugRecord = $this->addContent($this->articles->id, 'en', [
            'title' => 'Numeric Slug Record', 'slug' => '7001',
        ], now(), 7002);

        // /articles/7001 is an ID lookup -> record 7001, never the slug match.
        $this->getJson('/api/project/blog/articles/7001')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $byId->id);

        // /articles/slug/7001 is a slug lookup -> the record whose slug is 7001.
        $this->getJson('/api/project/blog/articles/slug/7001')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $numericSlugRecord->id);
    }

    public function test_reserved_word_slug_is_addressable_via_slug_endpoint(): void
    {
        $article = $this->addContent($this->articles->id, 'en', [
            'title' => 'Search Slug', 'slug' => 'search',
        ], now());

        // The literal "slug" segment is registered before the relation route,
        // so /articles/slug/search resolves the record instead of being
        // captured as an ID/related pair.
        $this->getJson('/api/project/blog/articles/slug/search')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $article->id);
    }

    // =================================================================
    // Validation: slug rules (uniqueness only; numeric / reserved words
    // are allowed because the slug endpoint is explicit)
    // =================================================================

    private function asProjectWithWriteAbility(): void
    {
        // Projects are the Sanctum tokenable, so authenticate exactly like
        // the real runtime: a Bearer token with the required abilities.
        $token = $this->project->createToken('test-token', ['create', 'read', 'update', 'delete']);
        $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken);
    }

    public function test_create_accepts_numeric_slug(): void
    {
        $this->asProjectWithWriteAbility();

        $response = $this->postJson('/api/project/blog/articles', [
            'title' => 'Numeric', 'slug' => '9001',
        ])->assertStatus(201);

        $id = $response->json('data.id');

        // Reachable via the slug endpoint, while the ID endpoint stays clean.
        $this->getJson('/api/project/blog/articles/slug/9001')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $id);
    }

    public function test_create_accepts_search_as_slug(): void
    {
        $this->asProjectWithWriteAbility();

        $response = $this->postJson('/api/project/blog/articles', [
            'title' => 'Reserved', 'slug' => 'search',
        ])->assertStatus(201);

        $this->getJson('/api/project/blog/articles/slug/search')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $response->json('data.id'));
    }

    public function test_create_rejects_duplicate_slug_in_same_collection(): void
    {
        $this->asProjectWithWriteAbility();

        $this->postJson('/api/project/blog/articles', [
            'title' => 'First', 'slug' => 'duplicate-slug',
        ])->assertStatus(201);

        $this->postJson('/api/project/blog/articles', [
            'title' => 'Second', 'slug' => 'duplicate-slug',
        ])->assertStatus(422);
    }

    public function test_create_accepts_legitimate_slug(): void
    {
        $this->asProjectWithWriteAbility();

        $this->postJson('/api/project/blog/articles', [
            'title' => 'Valid', 'slug' => 'my-valid-article',
        ])->assertStatus(201);
    }

    // =================================================================
    // Write endpoints stay ID-only
    // =================================================================

    public function test_delete_requires_numeric_id_and_does_not_accept_slug(): void
    {
        $this->asProjectWithWriteAbility();
        $article = $this->addContent($this->articles->id, 'en', [
            'title' => 'To Delete', 'slug' => 'to-delete',
        ], now());

        // Deleting by slug does not resolve (ID-only write path) -> 404.
        $this->deleteJson('/api/project/blog/articles/to-delete')
            ->assertStatus(404);

        // Deleting by ID still works.
        $this->deleteJson('/api/project/blog/articles/' . $article->id)
            ->assertStatus(200);
    }

    // =================================================================
    // Literal-segment routes are not captured by content routes
    // =================================================================

    public function test_media_literal_route_is_not_captured_as_content(): void
    {
        $media = Media::create([
            'project_id' => $this->project->id,
            'name' => 'photo.jpg',
            'type' => 'image/jpeg',
            'size' => 1024,
            'disk' => 'local',
        ]);

        $this->getJson('/api/project/blog/media/' . $media->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $media->id);
    }

    // =================================================================
    // Relation by slug: GET /{collection}/slug/{slug}/{related}
    // =================================================================

    public function test_relation_by_slug_resolves_the_same_as_by_id(): void
    {
        $categories = Collection::create([
            'name' => 'Categories', 'slug' => 'categories',
            'project_id' => $this->project->id, 'order' => 2,
        ]);
        $this->addField($categories, 'title', 'text', ['required' => true]);
        $this->addField($categories, 'slug', 'slug', ['required' => true, 'unique' => true]);

        CollectionField::create([
            'type' => 'relation',
            'label' => 'Category',
            'name' => 'category',
            'options' => json_encode([
                'relation' => ['type' => 1, 'collection' => (string) $categories->id],
                'slug' => [], 'media' => [], 'enumeration' => [],
                'hideInContentList' => false,
            ]),
            'validations' => json_encode([
                'required' => ['status' => false, 'message' => null],
                'charcount' => ['status' => false, 'type' => '', 'min' => null, 'max' => null],
                'unique' => ['status' => false, 'message' => null],
            ]),
            'project_id' => $this->project->id,
            'collection_id' => $this->articles->id,
            'order' => 3,
        ]);

        $category = $this->addContent($categories->id, 'en', [
            'title' => 'News', 'slug' => 'news-zh',
        ], now());

        $article = $this->addContent($this->articles->id, 'en', [
            'title' => 'Security Guide', 'slug' => 'security-guide',
            'category' => (string) $category->id,
        ], now());

        // Slug-source relation endpoint resolves the source by slug.
        $this->getJson('/api/project/blog/categories/slug/news-zh/articles')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $article->id);

        // ID relation endpoint returns the same list.
        $this->getJson('/api/project/blog/categories/' . $category->id . '/articles')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $article->id);

        // Unknown source slug -> 404.
        $this->getJson('/api/project/blog/categories/slug/does-not-exist/articles')
            ->assertStatus(404);

        // The literal slug relation route is not captured by the ID relation
        // route (4 segments both) — a numeric-looking slug still resolves.
        $this->getJson('/api/project/blog/categories/slug/news-zh/articles')
            ->assertStatus(200);
    }

    public function test_relation_field_without_type_option_does_not_500(): void
    {
        $categories = Collection::create([
            'name' => 'Categories', 'slug' => 'categories',
            'project_id' => $this->project->id, 'order' => 2,
        ]);
        $this->addField($categories, 'title', 'text', ['required' => true]);
        $this->addField($categories, 'slug', 'slug', ['required' => true, 'unique' => true]);

        CollectionField::create([
            'type' => 'relation',
            'label' => 'Category',
            'name' => 'category',
            'options' => json_encode([
                'relation' => ['collection' => (string) $categories->id],
                'slug' => [], 'media' => [], 'enumeration' => [],
                'hideInContentList' => false,
            ]),
            'validations' => json_encode([
                'required' => ['status' => false, 'message' => null],
                'charcount' => ['status' => false, 'type' => '', 'min' => null, 'max' => null],
                'unique' => ['status' => false, 'message' => null],
            ]),
            'project_id' => $this->project->id,
            'collection_id' => $this->articles->id,
            'order' => 3,
        ]);

        $category = $this->addContent($categories->id, 'en', [
            'title' => 'News', 'slug' => 'news-zh',
        ], now());

        $article = $this->addContent($this->articles->id, 'en', [
            'title' => 'Legacy Article', 'slug' => 'legacy-article',
            'category' => (string) $category->id,
        ], now());

        $this->getJson('/api/project/blog/categories/slug/news-zh/articles')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $article->id)
            ->assertJsonPath('data.0.category.id', $category->id);
    }
}
