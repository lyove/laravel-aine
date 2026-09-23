<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ContentController;
use App\Models\Collection;
use App\Models\CollectionField;
use App\Models\Content;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke: publish/unpublish actions require admin (issue 7).
 */
class PublishPermissionSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;
    private Content $content;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin']);

        $owner = User::create(['name' => 'Owner', 'email' => 'owner@perm.test', 'password' => bcrypt('password')]);
        $this->project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'status' => 1, 'public_api' => 1, 'owner_id' => $owner->id]);

        $collection = Collection::create(['name' => 'Articles', 'slug' => 'articles', 'project_id' => $this->project->id]);
        CollectionField::create([
            'type' => 'text', 'label' => 'Title', 'name' => 'title',
            'options' => json_encode(['text' => ['type' => 1]]),
            'validations' => json_encode(['required' => ['status' => false, 'message' => null]]),
            'project_id' => $this->project->id, 'collection_id' => $collection->id, 'order' => 1,
        ]);

        $this->content = Content::create([
            'project_id' => $this->project->id,
            'collection_id' => $collection->id,
            'locale' => 'en',
            'published_at' => now(),
        ]);
    }

    private function makeEditor(): User
    {
        $editor = User::create(['name' => 'Editor', 'email' => 'editor@perm.test', 'password' => bcrypt('password')]);
        $this->project->members()->attach($editor->id, ['role' => 'editor']);
        return $editor;
    }

    private function makeAdmin(): User
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@perm.test', 'password' => bcrypt('password')]);
        $this->project->members()->attach($admin->id, ['role' => 'admin']);
        return $admin;
    }

    public function test_editor_cannot_unpublish(): void
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->actingAs($this->makeEditor());
        $collection = Collection::where('project_id', $this->project->id)->first();
        $this->app->make(ContentController::class)->unpublish($this->project->id, $collection->id, $this->content->id);
    }

    public function test_admin_can_unpublish(): void
    {
        $this->actingAs($this->makeAdmin());
        $collection = Collection::where('project_id', $this->project->id)->first();
        $response = $this->app->make(ContentController::class)->unpublish($this->project->id, $collection->id, $this->content->id);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($this->content->fresh()->published_at);
    }
}
