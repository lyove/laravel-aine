<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression tests for the user / project permission model:
 *
 *   - self-registration assigns the default "user" role
 *   - project creation makes the creator the owner (owner_id + project_user)
 *   - the project list is row-level isolated (owner/member only)
 *   - the project role matrix is enforced through policies
 */
class ProjectAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin']);
        Role::firstOrCreate(['name' => 'user']);
    }

    private function makeUser(string $email = 'user@test.local'): User
    {
        $user = User::create(['name' => 'User', 'email' => $email, 'password' => bcrypt('password')]);
        $user->assignRole('user');

        return $user;
    }

    private function makeProjectWithOwner(User $owner): Project
    {
        $project = Project::create(['name' => 'Mine', 'slug' => 'mine', 'owner_id' => $owner->id]);
        ProjectUser::create([
            'project_id' => $project->id,
            'user_id' => $owner->id,
            'role' => ProjectUser::ROLE_OWNER,
        ]);

        return $project;
    }

    public function test_registration_assigns_default_user_role(): void
    {
        $response = $this->post('/register', [
            'name' => 'Newbie',
            'email' => 'newbie@test.local',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertRedirect();

        $user = User::where('email', 'newbie@test.local')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->hasRole('super_admin'));
        // A freshly registered user must not hold any per-project role.
        $this->assertCount(0, ProjectUser::where('user_id', $user->id)->get());
    }

    public function test_any_logged_in_user_can_create_project_and_becomes_owner(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $response = $this->postJson('/admin-api/projects', [
            'name' => 'My First Project',
            'default_locale' => 'en',
        ]);

        $response->assertStatus(200);

        $project = Project::where('name', 'My First Project')->firstOrFail();
        $this->assertSame($user->id, $project->owner_id);

        $membership = ProjectUser::where('project_id', $project->id)->where('user_id', $user->id)->first();
        $this->assertNotNull($membership);
        $this->assertSame(ProjectUser::ROLE_OWNER, $membership->role);
    }

    public function test_project_list_is_scoped_to_owner_and_members(): void
    {
        $owner = $this->makeUser('owner@test.local');
        $member = $this->makeUser('member@test.local');
        $stranger = $this->makeUser('stranger@test.local');

        $owned = $this->makeProjectWithOwner($owner);
        $joined = Project::create(['name' => 'Joined', 'slug' => 'joined', 'owner_id' => $owner->id]);
        ProjectUser::create([
            'project_id' => $joined->id,
            'user_id' => $member->id,
            'role' => ProjectUser::ROLE_EDITOR,
        ]);
        // A project that belongs to somebody else entirely.
        $other = Project::create(['name' => 'Other', 'slug' => 'other', 'owner_id' => $stranger->id]);
        ProjectUser::create([
            'project_id' => $other->id,
            'user_id' => $stranger->id,
            'role' => ProjectUser::ROLE_OWNER,
        ]);

        // Owner sees owned + joined (member) projects.
        $this->actingAs($owner);
        $ids = collect($this->getJson('/admin-api/projects')->json())->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$owned->id, $joined->id], $ids);

        // Member sees only the project they joined.
        $this->actingAs($member);
        $ids = collect($this->getJson('/admin-api/projects')->json())->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$joined->id], $ids);

        // Stranger sees only their own project.
        $this->actingAs($stranger);
        $ids = collect($this->getJson('/admin-api/projects')->json())->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$other->id], $ids);
    }

    public function test_non_member_cannot_view_update_or_delete_a_project(): void
    {
        $owner = $this->makeUser('owner@test.local');
        $stranger = $this->makeUser('stranger@test.local');
        $project = $this->makeProjectWithOwner($owner);

        $this->actingAs($stranger);

        $this->getJson('/admin-api/projects/' . $project->id)->assertStatus(403);
        $this->postJson('/admin-api/projects/update/' . $project->id, ['name' => 'Hacked'])->assertStatus(403);
        $this->deleteJson('/admin-api/projects/delete/' . $project->id)->assertStatus(403);
    }

    public function test_owner_can_update_and_delete_own_project(): void
    {
        $owner = $this->makeUser();
        $project = $this->makeProjectWithOwner($owner);

        $this->actingAs($owner);

        $this->postJson('/admin-api/projects/update/' . $project->id, [
            'name' => 'Renamed',
            'slug' => 'renamed',
            'disk' => 'local',
        ])->assertStatus(200);

        $this->assertSame('Renamed', $project->fresh()->name);

        $this->deleteJson('/admin-api/projects/delete/' . $project->id)->assertStatus(200);
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_editor_cannot_manage_settings_members_or_delete(): void
    {
        $owner = $this->makeUser('owner@test.local');
        $editor = $this->makeUser('editor@test.local');
        $project = $this->makeProjectWithOwner($owner);

        ProjectUser::create([
            'project_id' => $project->id,
            'user_id' => $editor->id,
            'role' => ProjectUser::ROLE_EDITOR,
        ]);

        $this->actingAs($editor);

        // Editors may view the project and its content…
        $this->getJson('/admin-api/projects/' . $project->id)->assertStatus(200);
        $this->assertSame(ProjectUser::ROLE_EDITOR, $this->getJson('/admin-api/projects/' . $project->id)->json('my_role'));

        // …but not project settings, member management or deletion.
        $this->getJson('/admin-api/projects/settings/locales/' . $project->id)->assertStatus(403);
        $this->postJson('/admin-api/projects/settings/users/assign/' . $project->id, [
            'user_id' => $owner->id,
            'role' => 'admin',
        ])->assertStatus(403);
        $this->deleteJson('/admin-api/projects/delete/' . $project->id)->assertStatus(403);
    }

    public function test_owner_manages_members_and_assignments_are_visible_in_project_list(): void
    {
        $owner = $this->makeUser('owner@test.local');
        $invitee = $this->makeUser('invitee@test.local');
        $project = $this->makeProjectWithOwner($owner);

        $this->actingAs($owner);

        // Assign the invitee as an editor.
        $this->postJson('/admin-api/projects/settings/users/assign/' . $project->id, [
            'user_id' => $invitee->id,
            'role' => 'editor',
        ])->assertStatus(200);

        $membership = ProjectUser::where('project_id', $project->id)->where('user_id', $invitee->id)->first();
        $this->assertNotNull($membership);
        $this->assertSame(ProjectUser::ROLE_EDITOR, $membership->role);

        // The invitee now sees the project in their own list.
        $this->actingAs($invitee);
        $ids = collect($this->getJson('/admin-api/projects')->json())->pluck('id')->all();
        $this->assertContains($project->id, $ids);
    }

    public function test_super_admin_can_access_any_project(): void
    {
        $super = User::create(['name' => 'Super', 'email' => 'super@test.local', 'password' => bcrypt('password')]);
        $super->assignRole('super_admin');

        $owner = $this->makeUser('owner@test.local');
        $project = $this->makeProjectWithOwner($owner);

        $this->actingAs($super);

        $this->getJson('/admin-api/projects/' . $project->id)->assertStatus(200);
        $this->deleteJson('/admin-api/projects/delete/' . $project->id)->assertStatus(200);
    }

    public function test_owner_transfers_ownership_to_another_member(): void
    {
        $owner = $this->makeUser('owner@test.local');
        $member = $this->makeUser('member@test.local');
        $project = $this->makeProjectWithOwner($owner);

        ProjectUser::create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => ProjectUser::ROLE_ADMIN,
        ]);

        $this->actingAs($owner);

        $this->postJson('/admin-api/projects/settings/users/transfer-owner/' . $project->id, [
            'user_id' => $member->id,
        ])->assertStatus(200);

        // Ownership moves; the new owner holds the "owner" membership.
        $this->assertSame($member->id, $project->fresh()->owner_id);
        $this->assertSame(
            ProjectUser::ROLE_OWNER,
            ProjectUser::where('project_id', $project->id)->where('user_id', $member->id)->value('role')
        );

        // The previous owner stays on as an admin member.
        $this->assertSame(
            ProjectUser::ROLE_ADMIN,
            ProjectUser::where('project_id', $project->id)->where('user_id', $owner->id)->value('role')
        );

        // The new owner now manages members.
        $this->actingAs($member);
        $this->getJson('/admin-api/projects/settings/users/' . $project->id)->assertStatus(200);
    }

    public function test_non_owner_cannot_transfer_ownership(): void
    {
        $owner = $this->makeUser('owner@test.local');
        $editor = $this->makeUser('editor@test.local');
        $project = $this->makeProjectWithOwner($owner);

        ProjectUser::create([
            'project_id' => $project->id,
            'user_id' => $editor->id,
            'role' => ProjectUser::ROLE_EDITOR,
        ]);

        $this->actingAs($editor);

        $this->postJson('/admin-api/projects/settings/users/transfer-owner/' . $project->id, [
            'user_id' => $editor->id,
        ])->assertStatus(403);

        $this->assertSame($owner->id, $project->fresh()->owner_id);
    }

    public function test_owner_row_has_admin_role_in_member_payload(): void
    {
        $owner = $this->makeUser('owner@test.local');
        $project = $this->makeProjectWithOwner($owner);

        $this->actingAs($owner);

        $response = $this->getJson('/admin-api/projects/settings/users/' . $project->id);
        $response->assertStatus(200);

        // my_role is exposed on the project payload for the settings UI.
        $this->assertSame(ProjectUser::ROLE_OWNER, $response->json('project.my_role'));
        $this->assertSame($owner->email, $response->json('owner.email'));
    }
}
