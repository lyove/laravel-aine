<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\CollectionField;
use App\Models\Content;
use App\Models\ContentMeta;
use App\Models\Favorite;
use App\Models\Like;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Frontend user interactions (favorites / likes) and the user profile:
 * idempotency, auth requirements, admin-area gating for backend roles.
 */
class FrontendInteractionsProfileTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;
    private int $articlesId;
    private int $commentsId;
    private int $articleId;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'user']);
        Role::firstOrCreate(['name' => 'editor']);
        Role::firstOrCreate(['name' => 'super_admin']);

        $this->project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'status' => 1, 'public_api' => 1]);

        $this->articlesId = Collection::create(['name' => 'Articles', 'slug' => 'articles', 'project_id' => $this->project->id])->id;
        $this->commentsId = Collection::create(['name' => 'Comments', 'slug' => 'comments', 'project_id' => $this->project->id])->id;

        $validations = json_encode([
            'required' => ['status' => false, 'message' => null],
            'charcount' => ['status' => false, 'type' => '', 'min' => null, 'max' => null],
            'unique' => ['status' => false, 'message' => null],
        ]);

        foreach (['title', 'slug'] as $i => $name) {
            CollectionField::create([
                'type' => 'text', 'label' => ucfirst($name), 'name' => $name,
                'options' => '{}', 'validations' => $validations,
                'project_id' => $this->project->id, 'collection_id' => $this->articlesId, 'order' => $i + 1,
            ]);
        }

        $article = Content::create([
            'project_id' => $this->project->id,
            'collection_id' => $this->articlesId,
            'locale' => 'en',
            'created_by' => 1,
            'updated_by' => 1,
            'published_at' => now(),
            'published_by' => 1,
        ]);
        $this->articleId = $article->id;
        ContentMeta::create(['project_id' => $this->project->id, 'collection_id' => $this->articlesId, 'content_id' => $article->id, 'field_name' => 'title', 'value' => 'Hello World']);
        ContentMeta::create(['project_id' => $this->project->id, 'collection_id' => $this->articlesId, 'content_id' => $article->id, 'field_name' => 'slug', 'value' => 'hello-world']);

        $this->user = User::create(['name' => 'Alice Brown', 'email' => 'alice@test.local', 'password' => bcrypt('password')]);
        $this->user->assignRole('user');
    }

    private function createComment(string $comment, bool $approved): int
    {
        $content = Content::create([
            'project_id' => $this->project->id,
            'collection_id' => $this->commentsId,
            'locale' => 'en',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);
        foreach ([
            'name' => 'Alice Brown',
            'e-mail' => 'alice@test.local',
            'comment' => $comment,
            'article' => (string) $this->articleId,
            'status' => $approved ? 'approved' : 'pending',
        ] as $field => $value) {
            ContentMeta::create(['project_id' => $this->project->id, 'collection_id' => $this->commentsId, 'content_id' => $content->id, 'field_name' => $field, 'value' => $value]);
        }

        return $content->id;
    }

    // -----------------------------------------------------------------
    // Favorites & likes
    // -----------------------------------------------------------------

    public function test_guest_cannot_favorite(): void
    {
        $this->postJson('/api/project/demo/favorites', ['content_id' => $this->articleId])
            ->assertStatus(401);
    }

    public function test_favorite_is_idempotent_and_removable(): void
    {
        $this->actingAs($this->user);

        $this->postJson('/api/project/demo/favorites', ['content_id' => $this->articleId])
            ->assertOk()
            ->assertJsonPath('data.is_favorited', true)
            ->assertJsonPath('data.favorite_count', 1);

        // Duplicate favorite keeps a single row.
        $this->postJson('/api/project/demo/favorites', ['content_id' => $this->articleId])
            ->assertOk()
            ->assertJsonPath('data.favorite_count', 1);
        $this->assertSame(1, Favorite::where('user_id', $this->user->id)->count());

        $this->deleteJson('/api/project/demo/favorites/'.$this->articleId)
            ->assertOk()
            ->assertJsonPath('data.is_favorited', false)
            ->assertJsonPath('data.favorite_count', 0);
        $this->assertSame(0, Favorite::where('user_id', $this->user->id)->count());

        // Removing again is a no-op, not an error.
        $this->deleteJson('/api/project/demo/favorites/'.$this->articleId)
            ->assertOk();
    }

    public function test_like_is_idempotent_and_removable(): void
    {
        $this->actingAs($this->user);

        $this->postJson('/api/project/demo/likes', ['content_id' => $this->articleId])
            ->assertOk()
            ->assertJsonPath('data.is_liked', true);

        $this->postJson('/api/project/demo/likes', ['content_id' => $this->articleId])
            ->assertOk()
            ->assertJsonPath('data.like_count', 1);
        $this->assertSame(1, Like::where('user_id', $this->user->id)->count());

        $this->deleteJson('/api/project/demo/likes/'.$this->articleId)
            ->assertOk()
            ->assertJsonPath('data.is_liked', false);
        $this->assertSame(0, Like::where('user_id', $this->user->id)->count());
    }

    public function test_cannot_favorite_unpublished_content(): void
    {
        $draft = Content::create([
            'project_id' => $this->project->id,
            'collection_id' => $this->articlesId,
            'locale' => 'en',
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        $this->actingAs($this->user);
        $this->postJson('/api/project/demo/favorites', ['content_id' => $draft->id])
            ->assertStatus(404);
    }

    public function test_state_endpoint_returns_counts_and_user_state(): void
    {
        $this->actingAs($this->user);
        $this->postJson('/api/project/demo/favorites', ['content_id' => $this->articleId]);
        $this->postJson('/api/project/demo/likes', ['content_id' => $this->articleId]);

        $this->getJson("/api/project/demo/interactions/{$this->articleId}")
            ->assertOk()
            ->assertJsonPath('data.favorite_count', 1)
            ->assertJsonPath('data.like_count', 1)
            ->assertJsonPath('data.is_favorited', true)
            ->assertJsonPath('data.is_liked', true);

        // Guests see counts but no user state.
        $this->actingAs(User::create(['name' => 'Guest', 'email' => 'guest@test.local', 'password' => bcrypt('password')]));
        $this->getJson("/api/project/demo/interactions/{$this->articleId}")
            ->assertOk()
            ->assertJsonPath('data.favorite_count', 1)
            ->assertJsonPath('data.is_favorited', false);
    }

    public function test_state_endpoint_rejects_unpublished_content(): void
    {
        $draft = Content::create([
            'project_id' => $this->project->id,
            'collection_id' => $this->articlesId,
            'locale' => 'en',
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        $this->getJson("/api/project/demo/interactions/{$draft->id}")->assertStatus(404);
    }

    public function test_state_endpoint_resolves_user_via_real_session(): void
    {
        Favorite::create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'content_id' => $this->articleId,
        ]);

        // Use a real session (not actingAs) so the route's `web` middleware
        // must resolve the authenticated user from the session; without it
        // is_favorited would always be false.
        $sessionKey = \Illuminate\Support\Facades\Auth::guard('web')->getName();

        $this->withSession([$sessionKey => $this->user->id])
            ->getJson("/api/project/demo/interactions/{$this->articleId}")
            ->assertOk()
            ->assertJsonPath('data.is_favorited', true);
    }

    // -----------------------------------------------------------------
    // Profile
    // -----------------------------------------------------------------

    public function test_profile_returns_info_and_counts(): void
    {
        $this->actingAs($this->user);
        $this->postJson('/api/project/demo/favorites', ['content_id' => $this->articleId]);
        $this->postJson('/api/project/demo/likes', ['content_id' => $this->articleId]);
        $this->createComment('Nice article', true);

        $this->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonPath('data.name', 'Alice Brown')
            ->assertJsonPath('data.favorites_count', 1)
            ->assertJsonPath('data.likes_count', 1)
            ->assertJsonPath('data.comments_count', 1);
    }

    public function test_profile_favorites_are_enriched(): void
    {
        $this->actingAs($this->user);
        $this->postJson('/api/project/demo/favorites', ['content_id' => $this->articleId]);

        $this->getJson('/api/me/favorites')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Hello World')
            ->assertJsonPath('data.0.slug', 'hello-world')
            ->assertJsonPath('data.0.collection', 'articles')
            ->assertJsonPath('data.0.project_identifier', 'demo')
            ->assertJsonPath('data.0.published', true);
    }

    public function test_profile_comments_lists_own_comments(): void
    {
        $this->actingAs($this->user);
        $this->createComment('My first comment', true);
        $this->createComment('Awaiting moderation', false);

        $data = $this->getJson('/api/me/comments')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json('data');

        $comments = array_column($data, 'comment');
        $this->assertContains('My first comment', $comments);
        $this->assertContains('Awaiting moderation', $comments);

        $approved = collect($data)->firstWhere('status', 'approved');
        $this->assertSame('Hello World', $approved['article_title']);
        $this->assertSame('hello-world', $approved['article_slug']);
        $this->assertSame('demo', $approved['project_identifier']);
    }

    public function test_avatar_upload_updates_user(): void
    {
        Storage::fake('local');
        $this->actingAs($this->user);

        $this->post('/api/me/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.png', 64, 64),
        ])->assertOk();

        $this->assertStringStartsWith('/storage/avatars/user_', $this->user->fresh()->avatar);
    }

    // -----------------------------------------------------------------
    // Admin-area gating
    // -----------------------------------------------------------------

    public function test_user_role_blocked_from_admin_api(): void
    {
        $this->actingAs($this->user);

        $this->getJson('/admin-api/user')
            ->assertStatus(403)
            ->assertJsonPath('code', 403);
    }

    public function test_user_role_redirected_from_admin_spa(): void
    {
        $this->actingAs($this->user);

        $this->get('/admin')
            ->assertRedirect('/');
    }

    public function test_editor_role_can_access_admin_api(): void
    {
        $editor = User::create(['name' => 'Editor One', 'email' => 'editor@test.local', 'password' => bcrypt('password')]);
        $editor->assignRole('editor');

        $this->actingAs($editor);

        $this->getJson('/admin-api/user')
            ->assertOk()
            ->assertJsonPath('name', 'Editor One')
            ->assertJsonPath('roles.0', 'editor');
    }

    public function test_editor_role_still_blocked_from_super_admin_only_routes(): void
    {
        $editor = User::create(['name' => 'Editor Two', 'email' => 'editor2@test.local', 'password' => bcrypt('password')]);
        $editor->assignRole('editor');

        $this->actingAs($editor);

        $this->getJson('/admin-api/users')
            ->assertStatus(403);
    }
}
