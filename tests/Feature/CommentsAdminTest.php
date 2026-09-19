<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\CollectionField;
use App\Models\Content;
use App\Models\ContentMeta;
use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Comment moderation in the admin area. Comment status is an explicit
 * field (pending / approved / spam / trash) aligned with mainstream
 * blog systems: owner/admin/editor can list, approve, mark spam, trash,
 * restore and bulk-moderate comments; viewers cannot.
 */
class CommentsAdminTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;
    private int $commentsId;
    private User $owner;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'user']);
        Role::firstOrCreate(['name' => 'editor']);

        $this->project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'status' => 1, 'public_api' => 1]);
        $this->commentsId = Collection::create(['name' => 'Comments', 'slug' => 'comments', 'project_id' => $this->project->id])->id;

        $validations = json_encode([
            'required' => ['status' => false, 'message' => null],
            'charcount' => ['status' => false, 'type' => '', 'min' => null, 'max' => null],
            'unique' => ['status' => false, 'message' => null],
        ]);
        foreach (['name', 'e-mail', 'comment', 'article', 'status'] as $i => $name) {
            CollectionField::create([
                'type' => 'text', 'label' => ucfirst($name), 'name' => $name,
                'options' => '{}', 'validations' => $validations,
                'project_id' => $this->project->id, 'collection_id' => $this->commentsId, 'order' => $i + 1,
            ]);
        }

        $this->owner = User::create(['name' => 'Owner', 'email' => 'owner@test.local', 'password' => bcrypt('password')]);
        $this->owner->assignRole('editor');
        ProjectUser::create(['project_id' => $this->project->id, 'user_id' => $this->owner->id, 'role' => ProjectUser::ROLE_OWNER]);

        $this->viewer = User::create(['name' => 'Viewer', 'email' => 'viewer@test.local', 'password' => bcrypt('password')]);
        $this->viewer->assignRole('user');
        ProjectUser::create(['project_id' => $this->project->id, 'user_id' => $this->viewer->id, 'role' => ProjectUser::ROLE_VIEWER]);
    }

    private function createComment(string $comment, string $status = 'pending'): int
    {
        $content = Content::create([
            'project_id' => $this->project->id,
            'collection_id' => $this->commentsId,
            'locale' => 'en',
            'created_by' => $this->owner->id,
            'updated_by' => $this->owner->id,
        ]);
        ContentMeta::create(['project_id' => $this->project->id, 'collection_id' => $this->commentsId, 'content_id' => $content->id, 'field_name' => 'name', 'value' => 'Alice']);
        ContentMeta::create(['project_id' => $this->project->id, 'collection_id' => $this->commentsId, 'content_id' => $content->id, 'field_name' => 'comment', 'value' => $comment]);
        ContentMeta::create(['project_id' => $this->project->id, 'collection_id' => $this->commentsId, 'content_id' => $content->id, 'field_name' => 'article', 'value' => '1']);
        ContentMeta::create(['project_id' => $this->project->id, 'collection_id' => $this->commentsId, 'content_id' => $content->id, 'field_name' => 'status', 'value' => $status]);

        return $content->id;
    }

    private function statusOf(int $contentId): ?string
    {
        return ContentMeta::where('content_id', $contentId)->where('field_name', 'status')->value('value');
    }

    public function test_filter_lists_matching_status_with_counts(): void
    {
        $this->createComment('Old approved', 'approved');
        $this->createComment('Needs review');

        $this->actingAs($this->owner);

        $response = $this->getJson("/admin-api/content/comments/{$this->project->id}?filter=pending")
            ->assertOk();
        $response->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.comment', 'Needs review')
            ->assertJsonPath('rows.0.status', 'pending')
            ->assertJsonPath('counts.pending', 1)
            ->assertJsonPath('counts.approved', 1);
    }

    public function test_approve_sets_status_approved(): void
    {
        $id = $this->createComment('Needs review');

        $this->actingAs($this->owner);

        $this->postJson("/admin-api/content/comments/approve/{$this->project->id}/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame('approved', $this->statusOf($id));
    }

    public function test_reject_moves_to_trash_status(): void
    {
        $id = $this->createComment('Spam');

        $this->actingAs($this->owner);

        $this->postJson("/admin-api/content/comments/reject/{$this->project->id}/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'trash');

        $this->assertSame('trash', $this->statusOf($id));
        $this->assertNull(Content::find($id)->deleted_at); // status-based, not soft-delete
    }

    public function test_spam_and_restore_cycle(): void
    {
        $id = $this->createComment('Dubious');

        $this->actingAs($this->owner);

        $this->postJson("/admin-api/content/comments/spam/{$this->project->id}/{$id}")->assertOk();
        $this->assertSame('spam', $this->statusOf($id));

        $this->postJson("/admin-api/content/comments/restore/{$this->project->id}/{$id}")->assertOk();
        $this->assertSame('pending', $this->statusOf($id));
    }

    public function test_bulk_approve_spam_trash_restore_delete(): void
    {
        $a = $this->createComment('One');
        $b = $this->createComment('Two');

        $this->actingAs($this->owner);

        $this->postJson("/admin-api/content/comments/bulk/{$this->project->id}", ['action' => 'approve', 'ids' => [$a, $b]])->assertOk();
        $this->assertSame('approved', $this->statusOf($a));
        $this->assertSame('approved', $this->statusOf($b));

        $this->postJson("/admin-api/content/comments/bulk/{$this->project->id}", ['action' => 'trash', 'ids' => [$a]])->assertOk();
        $this->assertSame('trash', $this->statusOf($a));

        $this->postJson("/admin-api/content/comments/bulk/{$this->project->id}", ['action' => 'restore', 'ids' => [$a]])->assertOk();
        $this->assertSame('pending', $this->statusOf($a));

        $this->postJson("/admin-api/content/comments/bulk/{$this->project->id}", ['action' => 'delete', 'ids' => [$b]])->assertOk();
        $this->assertDatabaseMissing('content', ['id' => $b]);
    }

    public function test_admin_content_index_filters_comments_by_status(): void
    {
        $approved = $this->createComment('Approved one', 'approved');
        $pending = $this->createComment('Pending one');

        $this->actingAs($this->owner);

        $this->getJson("/admin-api/content/{$this->project->id}/{$this->commentsId}?getItems=pending&locale=all")
            ->assertOk()
            ->assertJsonPath('pending', 1)
            ->assertJsonPath('approved', 1)
            ->assertJsonPath('content.data.0.id', $pending)
            ->assertJsonPath('content.data.0.status', 'pending');

        $this->getJson("/admin-api/content/{$this->project->id}/{$this->commentsId}?getItems=approved&locale=all")
            ->assertOk()
            ->assertJsonPath('content.data.0.id', $approved)
            ->assertJsonPath('content.data.0.status', 'approved');
    }

    public function test_viewer_cannot_list_or_moderate(): void
    {
        $id = $this->createComment('Needs review');

        $this->actingAs($this->viewer);

        $this->getJson("/admin-api/content/comments/{$this->project->id}")->assertStatus(403);
        $this->postJson("/admin-api/content/comments/approve/{$this->project->id}/{$id}")->assertStatus(403);
        $this->postJson("/admin-api/content/comments/reject/{$this->project->id}/{$id}")->assertStatus(403);
        $this->postJson("/admin-api/content/comments/spam/{$this->project->id}/{$id}")->assertStatus(403);
        $this->postJson("/admin-api/content/comments/bulk/{$this->project->id}", ['action' => 'approve', 'ids' => [$id]])->assertStatus(403);
    }
}
