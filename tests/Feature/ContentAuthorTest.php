<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ContentController;
use App\Models\Collection;
use App\Models\CollectionField;
use App\Models\Content;
use App\Models\ContentMeta;
use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Author field on content models (Articles/Pages) is a plain text
 * field filled automatically with the current user's name on create —
 * there is no separate Authors collection anymore.
 */
class ContentAuthorTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;
    private Collection $collection;
    private int $collectionId;
    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'user']);

        $this->project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'status' => 1, 'public_api' => 1]);

        $this->collection = Collection::create(['name' => 'Articles', 'slug' => 'articles', 'project_id' => $this->project->id]);
        $this->collectionId = $this->collection->id;

        $validations = json_encode([
            'required' => ['status' => false, 'message' => null],
            'charcount' => ['status' => false, 'type' => '', 'min' => null, 'max' => null],
            'unique' => ['status' => false, 'message' => null],
        ]);

        foreach ([
            ['type' => 'text', 'label' => 'Title', 'name' => 'title'],
            ['type' => 'text', 'label' => 'Author', 'name' => 'author'],
        ] as $i => $field) {
            CollectionField::create([
                'type' => $field['type'], 'label' => $field['label'], 'name' => $field['name'],
                'options' => '{}', 'validations' => $validations,
                'project_id' => $this->project->id, 'collection_id' => $this->collectionId, 'order' => $i + 1,
            ]);
        }

        $this->author = User::create(['name' => 'Jane Author', 'email' => 'jane@test.local', 'password' => bcrypt('password')]);
        $this->author->assignRole('user');
        ProjectUser::create([
            'project_id' => $this->project->id,
            'user_id' => $this->author->id,
            'role' => ProjectUser::ROLE_OWNER,
        ]);

        $this->actingAs($this->author);
    }

    public function test_store_sets_author_to_current_user(): void
    {
        $response = (new ContentController())->store(
            $this->project->id,
            $this->collectionId,
            Request::create('/x', 'POST', [
                'locale' => 'en',
                'published' => false,
                'data' => ['title' => 'Hello'],
            ])
        );
        $content = json_decode($response->getContent());

        $this->assertSame(
            'Jane Author',
            ContentMeta::where('content_id', $content->id)->where('field_name', 'author')->value('value')
        );
    }

    public function test_store_via_http_sets_author(): void
    {
        $response = $this->postJson("/admin-api/content/store/{$this->project->id}/{$this->collectionId}", [
            'locale' => 'en',
            'published' => false,
            'data' => ['title' => 'HTTP Hello'],
        ]);

        $response->assertOk();
        $id = $response->json('id');

        $this->assertSame(
            'Jane Author',
            ContentMeta::where('content_id', $id)->where('field_name', 'author')->value('value')
        );
    }

    public function test_update_keeps_original_author(): void
    {
        $response = (new ContentController())->store(
            $this->project->id,
            $this->collectionId,
            Request::create('/x', 'POST', [
                'locale' => 'en',
                'published' => false,
                'data' => ['title' => 'Hello'],
            ])
        );
        $content = json_decode($response->getContent());

        (new ContentController())->update(
            $this->project->id,
            $this->collectionId,
            $content->id,
            Request::create('/x', 'POST', [
                'locale' => 'en',
                'published' => false,
                'data' => ['title' => 'Updated'],
            ])
        );

        $this->assertSame(
            'Jane Author',
            ContentMeta::where('content_id', $content->id)->where('field_name', 'author')->value('value')
        );
    }
}
