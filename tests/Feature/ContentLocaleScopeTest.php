<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\CollectionField;
use App\Models\Content;
use App\Models\ContentMeta;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public content APIs must return exactly ONE language per request:
 *
 *   1. explicit `locale` request parameter  (e.g. ?locale=zh)
 *   2. legacy `filters.locale` filter (used by the bundled SPA)
 *   3. otherwise the project's default locale
 *
 * This applies to every read endpoint: list, single, related, search,
 * portal and the sitemap/feed endpoints — for CMS content (pages, articles,
 * categories, tags, comments, ...) and Business Directory content alike.
 */
class ContentLocaleScopeTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;
    private Collection $categories;
    private Collection $articles;
    private Collection $pages;

    private Content $enCategory;
    private Content $zhCategory;
    private Content $enArticle;
    private Content $zhArticle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::create([
            'name' => 'Blog', 'slug' => 'blog',
            'status' => 1, 'public_api' => 1,
            'default_locale' => 'en', 'locales' => 'en,zh',
        ]);

        $this->categories = Collection::create(['name' => 'Categories', 'slug' => 'categories', 'project_id' => $this->project->id, 'order' => 1]);
        $this->addField($this->categories, 'title', 'text');
        $this->addField($this->categories, 'slug', 'slug');

        $this->articles = Collection::create(['name' => 'Articles', 'slug' => 'articles', 'project_id' => $this->project->id, 'order' => 2]);
        $this->addField($this->articles, 'title', 'text');
        $this->addField($this->articles, 'category', 'relation', ['relation' => ['type' => 1, 'collection' => (string) $this->categories->id]]);

        $this->pages = Collection::create(['name' => 'Pages', 'slug' => 'pages', 'project_id' => $this->project->id, 'order' => 3]);
        $this->addField($this->pages, 'title', 'text');

        // The same slug exists in both languages as distinct rows/IDs.
        $this->enCategory = $this->addContent($this->categories->id, 'en', ['title' => 'Tech', 'slug' => 'tech']);
        $this->zhCategory = $this->addContent($this->categories->id, 'zh', ['title' => '科技', 'slug' => 'tech']);

        $this->enArticle = $this->addContent($this->articles->id, 'en', ['title' => 'Hello EN', 'category' => (string) $this->enCategory->id]);
        $this->zhArticle = $this->addContent($this->articles->id, 'zh', ['title' => '你好 ZH', 'category' => (string) $this->zhCategory->id]);

        $this->addContent($this->pages->id, 'en', ['title' => 'About EN']);
        $this->addContent($this->pages->id, 'zh', ['title' => '关于 ZH']);
    }

    private function addField(Collection $collection, string $name, string $type, array $extraOptions = []): void
    {
        $options = array_merge([
            'slug' => [], 'media' => [], 'relation' => [], 'enumeration' => [], 'hideInContentList' => false,
        ], $extraOptions);

        CollectionField::create([
            'type' => $type, 'label' => ucfirst($name), 'name' => $name,
            'options' => json_encode($options),
            'validations' => json_encode(['required' => ['status' => false, 'message' => null]]),
            'project_id' => $this->project->id, 'collection_id' => $collection->id, 'order' => 1,
        ]);
    }

    private function addContent(int $collectionId, string $locale, array $fields): Content
    {
        $content = Content::create([
            'project_id' => $this->project->id,
            'collection_id' => $collectionId,
            'locale' => $locale,
            'published_at' => now(),
        ]);

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
    // List
    // =================================================================

    public function test_list_defaults_to_project_default_locale(): void
    {
        $response = $this->getJson('/api/project/blog/articles');
        $response->assertStatus(200)->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('en', $data[0]['locale']);
        $this->assertSame('Hello EN', $data[0]['title']);
    }

    public function test_list_honors_explicit_locale_param(): void
    {
        $response = $this->getJson('/api/project/blog/articles?locale=zh');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('zh', $data[0]['locale']);
        $this->assertSame('你好 ZH', $data[0]['title']);
    }

    public function test_list_honors_legacy_filters_locale(): void
    {
        $response = $this->getJson('/api/project/blog/articles?filters.locale=zh');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('zh', $data[0]['locale']);
        $this->assertSame('你好 ZH', $data[0]['title']);
    }

    public function test_list_categories_are_locale_scoped_too(): void
    {
        $response = $this->getJson('/api/project/blog/categories');
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Tech', $data[0]['title']);
    }

    // =================================================================
    // locale=all — explicit opt-out of the single-language scope
    // =================================================================

    public function test_list_locale_all_returns_every_locale(): void
    {
        $response = $this->getJson('/api/project/blog/articles?locale=all');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        $titles = array_column($data, 'title');
        $this->assertContains('Hello EN', $titles);
        $this->assertContains('你好 ZH', $titles);
    }

    public function test_list_filters_locale_all_returns_every_locale(): void
    {
        $response = $this->getJson('/api/project/blog/articles?filters.locale=all');
        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_single_content_locale_all(): void
    {
        // The zh row is unreachable under the default scope but reachable
        // when `locale=all` explicitly opts out of the language scope.
        $this->getJson('/api/project/blog/articles/' . $this->zhArticle->id . '?locale=all')
            ->assertStatus(200)
            ->assertJsonPath('data.locale', 'zh')
            ->assertJsonPath('data.title', '你好 ZH');
    }

    public function test_search_locale_all_spans_every_locale(): void
    {
        $response = $this->getJson('/api/project/blog/articles/search?query=ZH&locale=all');
        $response->assertStatus(200)->assertJsonPath('total', 1);
        $this->assertSame('你好 ZH', $response->json('data.0.title'));

        $response = $this->getJson('/api/project/blog/articles/search?query=HELLO&locale=all');
        $response->assertJsonPath('total', 1);
        $this->assertSame('Hello EN', $response->json('data.0.title'));
    }

    public function test_portal_locale_all_returns_every_locale(): void
    {
        $response = $this->getJson('/api/project/blog/portal?locale=all');
        $response->assertStatus(200);

        $categories = $response->json('data.categories');
        $this->assertCount(2, $categories);
    }

    public function test_rss_locale_all_returns_every_locale(): void
    {
        $body = $this->get('/api/project/blog/feed.xml?collection=articles&locale=all')->getContent();
        $this->assertStringContainsString('Hello EN', $body);
        $this->assertStringContainsString('你好 ZH', $body);
    }

    // =================================================================
    // Single content
    // =================================================================

    public function test_single_content_defaults_to_default_locale(): void
    {
        $this->getJson('/api/project/blog/articles/' . $this->enArticle->id)
            ->assertStatus(200)
            ->assertJsonPath('data.locale', 'en');

        // The other language's row is not reachable under the default locale.
        $this->getJson('/api/project/blog/articles/' . $this->zhArticle->id)
            ->assertStatus(404);
    }

    public function test_single_content_honors_locale_param(): void
    {
        $this->getJson('/api/project/blog/articles/' . $this->zhArticle->id . '?locale=zh')
            ->assertStatus(200)
            ->assertJsonPath('data.locale', 'zh')
            ->assertJsonPath('data.title', '你好 ZH');
    }

    // =================================================================
    // Search
    // =================================================================

    public function test_search_defaults_to_default_locale(): void
    {
        $this->getJson('/api/project/blog/articles/search?query=HELLO')
            ->assertStatus(200)
            ->assertJsonPath('total', 1);

        // "你好" only exists in the zh row — invisible under the default locale.
        $this->getJson('/api/project/blog/articles/search?query=你好')
            ->assertJsonPath('total', 0);
    }

    public function test_search_honors_locale_param(): void
    {
        $response = $this->getJson('/api/project/blog/articles/search?query=你好&locale=zh');
        $response->assertStatus(200)->assertJsonPath('total', 1);
        $this->assertSame('你好 ZH', $response->json('data.0.title'));

        // English row is out of scope when searching zh.
        $this->getJson('/api/project/blog/articles/search?query=HELLO&locale=zh')
            ->assertJsonPath('total', 0);
    }

    // =================================================================
    // Related content (category -> articles)
    // =================================================================

    public function test_related_content_defaults_to_default_locale(): void
    {
        $response = $this->getJson('/api/project/blog/categories/' . $this->enCategory->id . '/articles');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Hello EN', $data[0]['title']);
    }

    public function test_related_content_honors_locale_param(): void
    {
        $response = $this->getJson('/api/project/blog/categories/' . $this->zhCategory->id . '/articles?locale=zh');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('你好 ZH', $data[0]['title']);
    }

    public function test_related_content_excludes_other_locale_rows(): void
    {
        // A zh row that points at the EN category — matched by relation but
        // must be excluded because the request scope is en.
        $this->addContent($this->articles->id, 'zh', ['title' => '跨语言 ZH', 'category' => (string) $this->enCategory->id]);

        $response = $this->getJson('/api/project/blog/categories/' . $this->enCategory->id . '/articles');
        $response->assertStatus(200);

        $titles = array_column($response->json('data'), 'title');
        $this->assertSame(['Hello EN'], $titles);

        // The same category under the zh scope returns the zh row only.
        $response = $this->getJson('/api/project/blog/categories/' . $this->enCategory->id . '/articles?locale=zh');
        $titles = array_column($response->json('data'), 'title');
        $this->assertSame(['跨语言 ZH'], $titles);
    }

    // =================================================================
    // Relation filter (filters.category.slug=...) — same slug, two locales
    // =================================================================

    public function test_relation_filter_matches_same_locale_slug_only(): void
    {
        $response = $this->getJson('/api/project/blog/articles?filters.category.slug=tech');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Hello EN', $data[0]['title']);

        $response = $this->getJson('/api/project/blog/articles?filters.category.slug=tech&locale=zh');
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('你好 ZH', $data[0]['title']);
    }

    // =================================================================
    // Portal
    // =================================================================

    public function test_portal_defaults_to_default_locale(): void
    {
        $response = $this->getJson('/api/project/blog/portal');
        $response->assertStatus(200);

        $categories = $response->json('data.categories');
        $this->assertNotEmpty($categories);
        $this->assertSame('Tech', $categories[0]['category']['title']);

        $pages = $response->json('data.pages');
        $this->assertSame('About EN', $pages[0]['title']);
    }

    public function test_portal_honors_locale_param(): void
    {
        $response = $this->getJson('/api/project/blog/portal?locale=zh');
        $response->assertStatus(200);

        $categories = $response->json('data.categories');
        $this->assertSame('科技', $categories[0]['category']['title']);

        $pages = $response->json('data.pages');
        $this->assertSame('关于 ZH', $pages[0]['title']);
    }

    // =================================================================
    // Feeds (RSS + sitemap)
    // =================================================================

    public function test_rss_defaults_to_default_locale(): void
    {
        $body = $this->get('/api/project/blog/feed.xml?collection=articles')->getContent();
        $this->assertStringContainsString('Hello EN', $body);
        $this->assertStringNotContainsString('你好 ZH', $body);

        $body = $this->get('/api/project/blog/feed.xml?collection=articles&locale=zh')->getContent();
        $this->assertStringContainsString('你好 ZH', $body);
        $this->assertStringNotContainsString('Hello EN', $body);
    }

    public function test_sitemap_defaults_to_default_locale(): void
    {
        $body = $this->get('/api/project/blog/sitemap.xml')->getContent();

        // Sitemaps carry URLs, not titles: the en row's URL is present,
        // the zh row's URL is not.
        $this->assertStringContainsString('id-' . $this->enArticle->id, $body);
        $this->assertStringNotContainsString('id-' . $this->zhArticle->id, $body);
    }
}
