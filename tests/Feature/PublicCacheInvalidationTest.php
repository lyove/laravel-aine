<?php

namespace Tests\Feature;

use App\Aine\PublicCache;
use App\Events\ContentCreated;
use App\Events\ContentDeleted;
use App\Events\ContentPublished;
use App\Events\ContentRestored;
use App\Events\ContentTrashed;
use App\Events\ContentUnpublished;
use App\Events\ContentUpdated;
use App\Listeners\BumpPublicCache;
use App\Models\Collection;
use App\Models\CollectionField;
use App\Models\Content;
use App\Models\ContentMeta;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Regression tests for P0-1: every content write (admin panel included)
 * must invalidate the public API cache by bumping the project version.
 */
class PublicCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;
    private Collection $articles;
    private Collection $pages;
    private Content $enArticle;
    private Content $zhArticle;
    private Content $enPage;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->project = Project::create([
            'name' => 'Blog', 'slug' => 'blog',
            'status' => 1, 'public_api' => 1,
            'default_locale' => 'en', 'locales' => 'en,zh',
        ]);

        $this->articles = Collection::create(['name' => 'Articles', 'slug' => 'articles', 'project_id' => $this->project->id, 'order' => 1]);
        $this->addField($this->articles, 'title', 'text');

        $this->pages = Collection::create(['name' => 'Pages', 'slug' => 'pages', 'project_id' => $this->project->id, 'order' => 2]);
        $this->addField($this->pages, 'title', 'text');

        $this->enArticle = $this->addContent($this->articles->id, 'en', ['title' => 'Hello EN']);
        $this->zhArticle = $this->addContent($this->articles->id, 'zh', ['title' => '你好 ZH']);
        $this->enPage = $this->addContent($this->pages->id, 'en', ['title' => 'Welcome Page']);
    }

    private function addField(Collection $collection, string $name, string $type): void
    {
        CollectionField::create([
            'type' => $type, 'label' => ucfirst($name), 'name' => $name,
            'options' => json_encode([
                'slug' => [], 'media' => [], 'relation' => [], 'enumeration' => [], 'hideInContentList' => false,
            ]),
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

    public function test_version_starts_at_zero(): void
    {
        $this->assertSame(0, PublicCache::version(1));
    }

    public function test_bump_increments_version(): void
    {
        PublicCache::bump(1);
        PublicCache::bump(1);
        $this->assertSame(2, PublicCache::version(1));
    }

    public function test_bump_is_scoped_per_project(): void
    {
        PublicCache::bump(1);
        PublicCache::bump(3);
        $this->assertSame(1, PublicCache::version(1));
        $this->assertSame(1, PublicCache::version(3));
        $this->assertSame(0, PublicCache::version(2));
    }

    public function test_listener_bumps_on_content_model_event(): void
    {
        $content = new Content();
        $content->project_id = 7;

        (new BumpPublicCache())->handle(new ContentUpdated([
            'source' => 'User',
            'content' => $content,
        ]));

        $this->assertSame(1, PublicCache::version(7));
    }

    public function test_listener_handles_plain_array_content(): void
    {
        (new BumpPublicCache())->handle(new ContentDeleted([
            'source' => 'User',
            'content' => [
                'project_id' => 9,
                'collection_id' => 3,
                'item_id' => 5,
            ],
        ]));

        $this->assertSame(1, PublicCache::version(9));
    }

    public function test_listener_ignores_content_without_project_id(): void
    {
        (new BumpPublicCache())->handle(new ContentDeleted([
            'source' => 'User',
            'content' => [],
        ]));

        $this->assertSame(0, PublicCache::version(1));
    }

    public function test_all_content_events_are_bound_to_the_listener(): void
    {
        Event::fake();

        Event::assertListening(ContentCreated::class, BumpPublicCache::class);
        Event::assertListening(ContentUpdated::class, BumpPublicCache::class);
        Event::assertListening(ContentTrashed::class, BumpPublicCache::class);
        Event::assertListening(ContentDeleted::class, BumpPublicCache::class);
        Event::assertListening(ContentPublished::class, BumpPublicCache::class);
        Event::assertListening(ContentUnpublished::class, BumpPublicCache::class);
        Event::assertListening(ContentRestored::class, BumpPublicCache::class);
    }

    // =================================================================
    // Feature-level: the public API response cache follows its inputs
    // =================================================================

    public function test_param_change_yields_fresh_cache_entry(): void
    {
        // Two different language inputs must never share a cache entry.
        $this->getJson('/api/project/blog/articles')->assertJsonPath('data.0.locale', 'en');
        $this->getJson('/api/project/blog/articles?locale=zh')->assertJsonPath('data.0.locale', 'zh');
        $this->getJson('/api/project/blog/articles')->assertJsonPath('data.0.locale', 'en');
    }

    public function test_default_locale_change_is_reflected_immediately(): void
    {
        // Warm the no-param cache entry under default_locale=en.
        $this->getJson('/api/project/blog/articles')->assertJsonPath('data.0.locale', 'en');

        // Backend switches the project's default language
        // (Admin\ProjectsController::changeDefaultLocale does exactly this).
        $this->project->update(['default_locale' => 'zh']);

        // The very next identical request must return zh. The language is an
        // input to the response, so the cache entry must change with it.
        $this->getJson('/api/project/blog/articles')->assertJsonPath('data.0.locale', 'zh');
    }

    public function test_content_edit_invalidates_cached_language_response(): void
    {
        // Warm the zh cache entry.
        $this->getJson('/api/project/blog/articles?locale=zh')->assertJsonPath('data.0.title', '你好 ZH');

        // Backend edits the zh content (mirrors the admin update path, which
        // dispatches ContentUpdated -> BumpPublicCache).
        $meta = $this->zhArticle->meta()->where('field_name', 'title')->first();
        $meta->update(['value' => '更新后的中文标题']);
        ContentUpdated::dispatch(['source' => 'User', 'content' => $this->zhArticle->fresh()]);

        // The next identical request must return the updated title.
        $this->getJson('/api/project/blog/articles?locale=zh')->assertJsonPath('data.0.title', '更新后的中文标题');
    }

    // =================================================================
    // Per-collection cache versions
    // =================================================================

    public function test_bump_is_scoped_per_collection(): void
    {
        PublicCache::bump(1, 'articles');
        PublicCache::bump(1, 'articles');
        PublicCache::bump(1, 'pages');

        $this->assertSame(2, PublicCache::version(1, 'articles'));
        $this->assertSame(1, PublicCache::version(1, 'pages'));
        $this->assertSame(0, PublicCache::version(1));          // project-wide version untouched
        $this->assertSame(0, PublicCache::version(1, 'unknown'));
    }

    public function test_listener_bumps_the_collection_of_content_model(): void
    {
        (new BumpPublicCache())->handle(new ContentUpdated([
            'source' => 'User',
            'content' => $this->enArticle,
        ]));

        $this->assertSame(1, PublicCache::version($this->project->id, 'articles'));
        $this->assertSame(0, PublicCache::version($this->project->id, 'pages'));
        $this->assertSame(0, PublicCache::version($this->project->id));
    }

    public function test_listener_bumps_the_collection_of_array_content(): void
    {
        (new BumpPublicCache())->handle(new ContentDeleted([
            'source' => 'User',
            'content' => [
                'project_id' => $this->project->id,
                'collection_id' => $this->pages->id,
                'item_id' => $this->enPage->id,
            ],
        ]));

        $this->assertSame(1, PublicCache::version($this->project->id, 'pages'));
        $this->assertSame(0, PublicCache::version($this->project->id, 'articles'));
        $this->assertSame(0, PublicCache::version($this->project->id));
    }

    public function test_content_edit_invalidates_only_its_own_collection_cache(): void
    {
        // Warm both collections' list caches.
        $this->getJson('/api/project/blog/articles')->assertJsonPath('data.0.title', 'Hello EN');
        $this->getJson('/api/project/blog/pages')->assertJsonPath('data.0.title', 'Welcome Page');

        // Edit the article title in the DB without dispatching any event.
        $this->enArticle->meta()->where('field_name', 'title')->update(['value' => 'Edited EN title']);

        // Nothing bumped yet → both still serve their cached entries.
        $this->getJson('/api/project/blog/articles')->assertJsonPath('data.0.title', 'Hello EN');
        $this->getJson('/api/project/blog/pages')->assertJsonPath('data.0.title', 'Welcome Page');

        // Dispatch the real update event for the article.
        ContentUpdated::dispatch(['source' => 'User', 'content' => $this->enArticle->fresh()]);

        // The articles cache is rebuilt…
        $this->getJson('/api/project/blog/articles')->assertJsonPath('data.0.title', 'Edited EN title');

        // …while the pages cache is untouched (per-collection isolation).
        $this->getJson('/api/project/blog/pages')->assertJsonPath('data.0.title', 'Welcome Page');
    }

    public function test_project_level_bump_invalidates_every_collection_cache(): void
    {
        // Warm both collections' list caches.
        $this->getJson('/api/project/blog/articles')->assertJsonPath('data.0.title', 'Hello EN');
        $this->getJson('/api/project/blog/pages')->assertJsonPath('data.0.title', 'Welcome Page');

        // Edit the page title in the DB silently.
        $this->enPage->meta()->where('field_name', 'title')->update(['value' => 'New Page Title']);

        // A project-wide bump (what locale management does) must invalidate
        // the caches of every collection.
        PublicCache::bump($this->project->id);

        $this->getJson('/api/project/blog/articles')->assertJsonPath('data.0.title', 'Hello EN');
        $this->getJson('/api/project/blog/pages')->assertJsonPath('data.0.title', 'New Page Title');
    }
}
