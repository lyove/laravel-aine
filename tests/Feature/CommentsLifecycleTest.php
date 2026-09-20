<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Content;
use App\Models\ContentMeta;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DemoProjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end comment lifecycle against the real demo data:
 * login → submit (auto/approval/disabled) → public visibility →
 * admin moderation (approve) → public visibility restored.
 */
class CommentsLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Project $cms;
    private int $articlesId;
    private int $commentsId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoProjectsSeeder::class);

        $this->cms = Project::where('slug', 'cms')->firstOrFail();
        $this->articlesId = Collection::where('project_id', $this->cms->id)->where('slug', 'articles')->firstOrFail()->id;
        $this->commentsId = Collection::where('project_id', $this->cms->id)->where('slug', 'comments')->firstOrFail()->id;
    }

    private function articleIdBySlug(string $slug): int
    {
        return (int) ContentMeta::where('collection_id', $this->articlesId)
            ->where('field_name', 'slug')
            ->where('value', $slug)
            ->firstOrFail()
            ->content_id;
    }

    private function articleModeration(int $articleId): ?string
    {
        return ContentMeta::where('content_id', $articleId)->where('field_name', 'comments_moderation')->value('value');
    }

    private function alice(): User
    {
        return User::where('email', 'alice@example.com')->firstOrFail();
    }

    public function test_full_comment_lifecycle(): void
    {
        $approvalArticle = $this->articleIdBySlug('getting-started-with-aine-cms');
        $autoArticle = $this->articleIdBySlug('building-a-blog-frontend-with-vue-3');
        $disabledArticle = $this->articleIdBySlug('aine-2-0-release-notes-what-s-new');

        $this->assertSame('approval', $this->articleModeration($approvalArticle));
        $this->assertNull($this->articleModeration($autoArticle)); // default = auto
        $this->assertSame('disabled', $this->articleModeration($disabledArticle));

        // Guest cannot submit.
        $this->postJson('/api/project/cms/comments', ['article_id' => $autoArticle, 'comment' => 'anon'])
            ->assertStatus(401);

        // Alice logs in.
        $this->actingAs($this->alice());

        // auto article → published immediately and visible.
        $this->postJson('/api/project/cms/comments', ['article_id' => $autoArticle, 'comment' => 'Auto-visible comment'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'approved');

        $this->getJson("/api/project/cms/comments/{$autoArticle}")
            ->assertOk()
            ->assertJsonFragment(['comment' => 'Auto-visible comment', 'name' => 'Alice Brown']);

        // approval article → pending, hidden from the public list.
        $this->postJson('/api/project/cms/comments', ['article_id' => $approvalArticle, 'comment' => 'Needs a review'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $pendingComment = Content::where('collection_id', $this->commentsId)->latest('id')->first();
        $this->assertSame('pending', ContentMeta::where('content_id', $pendingComment->id)->where('field_name', 'status')->value('value'));
        $this->assertSame($this->alice()->id, $pendingComment->created_by);
        $this->assertSame('Alice Brown', ContentMeta::where('content_id', $pendingComment->id)->where('field_name', 'name')->value('value'));

        $this->getJson("/api/project/cms/comments/{$approvalArticle}")
            ->assertOk()
            ->assertJsonMissing(['comment' => 'Needs a review']);

        // disabled article → rejected.
        $this->postJson('/api/project/cms/comments', ['article_id' => $disabledArticle, 'comment' => 'Nope'])
            ->assertStatus(422);

        // /api/auth/me returns the logged-in user.
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.name', 'Alice Brown');

        // --- Admin moderation ---
        $admin = User::where('email', 'admin@admin.com')->firstOrFail();
        $this->actingAs($admin);

        $this->getJson("/admin-api/content/comments/{$this->cms->id}?filter=pending")
            ->assertOk()
            ->assertJsonPath('rows.0.comment', 'Needs a review')
            ->assertJsonPath('rows.0.status', 'pending')
            ->assertJsonPath('counts.pending', 3); // 2 seeded + 1 new

        $this->postJson("/admin-api/content/comments/approve/{$this->cms->id}/{$pendingComment->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame('approved', ContentMeta::where('content_id', $pendingComment->id)->where('field_name', 'status')->value('value'));

        // Back to guest view: the approved comment is now public.
        auth()->logout();
        $this->getJson("/api/project/cms/comments/{$approvalArticle}")
            ->assertOk()
            ->assertJsonFragment(['comment' => 'Needs a review', 'name' => 'Alice Brown']);
    }

    public function test_reject_removes_comment_everywhere(): void
    {
        $approvalArticle = $this->articleIdBySlug('getting-started-with-aine-cms');

        $this->actingAs($this->alice());
        $this->postJson('/api/project/cms/comments', ['article_id' => $approvalArticle, 'comment' => 'To be removed'])
            ->assertStatus(201);
        $comment = Content::where('collection_id', $this->commentsId)->latest('id')->first();

        $admin = User::where('email', 'admin@admin.com')->firstOrFail();
        $this->actingAs($admin);
        $this->postJson("/admin-api/content/comments/reject/{$this->cms->id}/{$comment->id}")->assertOk();

        $this->assertSame('trash', ContentMeta::where('content_id', $comment->id)->where('field_name', 'status')->value('value'));
        $this->assertNull($comment->fresh()->deleted_at);
    }
}
