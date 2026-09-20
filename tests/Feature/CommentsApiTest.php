<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\CollectionField;
use App\Models\Content;
use App\Models\ContentMeta;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Blog comments on the public API: only logged-in users can submit,
 * visibility depends on the article's comments_moderation setting
 * (auto → published immediately, approval → pending, disabled → rejected).
 */
class CommentsApiTest extends TestCase
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

        $this->project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'status' => 1, 'public_api' => 1]);

        $this->articlesId = Collection::create(['name' => 'Articles', 'slug' => 'articles', 'project_id' => $this->project->id])->id;
        $this->commentsId = Collection::create(['name' => 'Comments', 'slug' => 'comments', 'project_id' => $this->project->id])->id;

        $validations = json_encode([
            'required' => ['status' => false, 'message' => null],
            'charcount' => ['status' => false, 'type' => '', 'min' => null, 'max' => null],
            'unique' => ['status' => false, 'message' => null],
        ]);

        foreach (['title', 'author'] as $i => $name) {
            CollectionField::create([
                'type' => 'text', 'label' => ucfirst($name), 'name' => $name,
                'options' => '{}', 'validations' => $validations,
                'project_id' => $this->project->id, 'collection_id' => $this->articlesId, 'order' => $i + 1,
            ]);
        }
        foreach (['name', 'e-mail', 'comment', 'article', 'status'] as $i => $name) {
            CollectionField::create([
                'type' => 'text', 'label' => ucfirst($name), 'name' => $name,
                'options' => '{}', 'validations' => $validations,
                'project_id' => $this->project->id, 'collection_id' => $this->commentsId, 'order' => $i + 1,
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
        ContentMeta::create(['project_id' => $this->project->id, 'collection_id' => $this->articlesId, 'content_id' => $article->id, 'field_name' => 'title', 'value' => 'Hello']);

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

    private function setModeration(?string $mode): void
    {
        if ($mode === null) {
            ContentMeta::where('content_id', $this->articleId)->where('field_name', 'comments_moderation')->delete();
        } else {
            ContentMeta::updateOrCreate(
                ['project_id' => $this->project->id, 'collection_id' => $this->articlesId, 'content_id' => $this->articleId, 'field_name' => 'comments_moderation'],
                ['value' => $mode]
            );
        }
    }

    public function test_guest_cannot_submit_comment(): void
    {
        $this->postJson("/api/project/demo/comments", ['article_id' => $this->articleId, 'comment' => 'hi'])
            ->assertStatus(401);
    }

    public function test_submit_auto_mode_publishes_immediately(): void
    {
        $this->actingAs($this->user);

        $this->postJson("/api/project/demo/comments", ['article_id' => $this->articleId, 'comment' => 'Nice post!'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'approved');

        $comment = Content::where('collection_id', $this->commentsId)->latest('id')->first();
        $this->assertSame('approved', ContentMeta::where('content_id', $comment->id)->where('field_name', 'status')->value('value'));
        $this->assertSame('Alice Brown', ContentMeta::where('content_id', $comment->id)->where('field_name', 'name')->value('value'));
        $this->assertSame('alice@test.local', ContentMeta::where('content_id', $comment->id)->where('field_name', 'e-mail')->value('value'));

        $this->getJson("/api/project/demo/comments/{$this->articleId}")
            ->assertOk()
            ->assertJsonPath('data.0.comment', 'Nice post!')
            ->assertJsonPath('data.0.status', 'approved');
    }

    public function test_submit_approval_mode_stays_pending_and_hidden(): void
    {
        $this->setModeration('approval');
        $this->actingAs($this->user);

        $this->postJson("/api/project/demo/comments", ['article_id' => $this->articleId, 'comment' => 'Waiting for approval'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $comment = Content::where('collection_id', $this->commentsId)->latest('id')->first();
        $this->assertSame('pending', ContentMeta::where('content_id', $comment->id)->where('field_name', 'status')->value('value'));

        $this->getJson("/api/project/demo/comments/{$this->articleId}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_submit_disabled_mode_rejected(): void
    {
        $this->setModeration('disabled');
        $this->actingAs($this->user);

        $this->postJson("/api/project/demo/comments", ['article_id' => $this->articleId, 'comment' => 'Nope'])
            ->assertStatus(422);
    }

    public function test_public_list_only_shows_approved_comments(): void
    {
        $this->createComment('Approved one', true);
        $this->createComment('Pending one', false);

        $this->getJson("/api/project/demo/comments/{$this->articleId}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.comment', 'Approved one');
    }

    public function test_index_rejects_missing_article(): void
    {
        $this->getJson("/api/project/demo/comments/99999")->assertStatus(404);
    }

    public function test_index_supports_limit_and_offset_pagination(): void
    {
        $this->createComment('First comment', true);
        $this->createComment('Second comment', true);
        $this->createComment('Third comment', true);

        // limit=2 → first two (oldest first)
        $this->getJson("/api/project/demo/comments/{$this->articleId}?limit=2")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.comment', 'First comment')
            ->assertJsonPath('data.1.comment', 'Second comment');

        // offset skips the first page; offset=2 → the last one
        $this->getJson("/api/project/demo/comments/{$this->articleId}?limit=2&offset=2")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.comment', 'Third comment');

        // Without limit the full list is still returned (backwards compatible).
        $this->getJson("/api/project/demo/comments/{$this->articleId}")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_auth_me_returns_user_when_logged_in(): void
    {
        $this->actingAs($this->user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'Alice Brown');
    }
}
