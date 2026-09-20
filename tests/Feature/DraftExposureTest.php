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
 * Hardening: drafts (published_at = null) must NEVER be exposed by the
 * public API — no matter what query params are passed. Only the admin
 * backend (App\Http\Controllers\Admin\ContentController) may see drafts.
 */
class DraftExposureTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;
    private Collection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::create([
            'name' => 'Demo', 'slug' => 'demo', 'status' => 1, 'public_api' => 1,
        ]);
        $this->collection = Collection::create([
            'name' => 'Articles', 'slug' => 'articles', 'project_id' => $this->project->id,
        ]);
        CollectionField::create([
            'type' => 'text', 'label' => 'Title', 'name' => 'title',
            'options' => json_encode(['text' => ['type' => 1]]),
            'validations' => json_encode(['required' => ['status' => false, 'message' => null]]),
            'project_id' => $this->project->id, 'collection_id' => $this->collection->id, 'order' => 1,
        ]);

        $this->makeContent(now(), ['title' => 'Published Post A', 'slug' => 'published-a']);
        $this->makeContent(now(), ['title' => 'Published Post B', 'slug' => 'published-b']);
        $this->makeContent(null, ['title' => 'Secret Draft', 'slug' => 'secret-draft']);
    }

    private function makeContent($publishedAt, array $fields): Content
    {
        $content = Content::create([
            'project_id' => $this->project->id,
            'collection_id' => $this->collection->id,
            'locale' => 'en',
            'published_at' => $publishedAt,
        ]);
        foreach ($fields as $name => $value) {
            ContentMeta::create([
                'project_id' => $this->project->id,
                'collection_id' => $this->collection->id,
                'content_id' => $content->id,
                'field_name' => $name,
                'value' => $value,
            ]);
        }
        return $content;
    }

    public function test_list_hides_draft_by_default(): void
    {
        $this->get('/api/project/demo/articles')
            ->assertStatus(200);
        $titles = array_column($this->get('/api/project/demo/articles')->json('data') ?? [], 'title');
        $this->assertNotContains('Secret Draft', $titles);
        $this->assertContains('Published Post A', $titles);
    }

    public function test_list_ignores_only_draft_param(): void
    {
        $titles = array_column(
            $this->get('/api/project/demo/articles?state=only_draft')->json('data') ?? [],
            'title'
        );
        $this->assertNotContains('Secret Draft', $titles);
        // Still returns the 2 published ones, not 1 draft.
        $this->assertContains('Published Post A', $titles);
        $this->assertContains('Published Post B', $titles);
    }

    public function test_detail_by_slug_returns_404_for_draft(): void
    {
        $this->get('/api/project/demo/articles/slug/secret-draft')->assertStatus(404);
    }

    public function test_detail_by_slug_returns_200_for_published(): void
    {
        $this->get('/api/project/demo/articles/slug/published-a')->assertStatus(200);
    }
}
