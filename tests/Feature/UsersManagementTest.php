<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\User;
use App\Aine\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Global user management (super admin only): list with memberships,
 * create, update (name/email/password/global role), delete — plus the
 * guards against removing yourself or the last super admin.
 */
class UsersManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $plainUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin']);
        Role::firstOrCreate(['name' => 'user']);

        $this->superAdmin = User::create(['name' => 'Boss', 'email' => 'boss@test.local', 'password' => bcrypt('secret')]);
        $this->superAdmin->assignRole('super_admin');

        $this->plainUser = User::create(['name' => 'Plain', 'email' => 'plain@test.local', 'password' => bcrypt('secret')]);
        $this->plainUser->assignRole('user');
    }

    public function test_non_super_admin_cannot_access_user_management(): void
    {
        $this->actingAs($this->plainUser);

        $this->getJson('/admin-api/users')->assertStatus(403);
        $this->postJson('/admin-api/users', ['name' => 'X', 'email' => 'x@test.local', 'password' => 'StrongPass1234!', 'role' => 'user'])->assertStatus(403);
        $this->postJson('/admin-api/users/1', ['name' => 'X', 'email' => 'x@test.local', 'role' => 'user'])->assertStatus(403);
        $this->deleteJson('/admin-api/users/1')->assertStatus(403);
    }

    public function test_super_admin_can_list_users_with_memberships(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'status' => 1, 'public_api' => 1]);
        ProjectUser::create(['project_id' => $project->id, 'user_id' => $this->plainUser->id, 'role' => ProjectUser::ROLE_EDITOR]);

        $this->actingAs($this->superAdmin);

        $this->getJson('/admin-api/users')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.data.0.global_role', 'super_admin')
            ->assertJsonPath('data.data.1.global_role', 'user')
            ->assertJsonPath('data.data.1.memberships.0.name', 'Demo')
            ->assertJsonPath('data.data.1.memberships.0.role', 'editor');
    }

    public function test_search_filters_users(): void
    {
        $this->actingAs($this->superAdmin);

        $this->getJson('/admin-api/users?search=plain')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.email', 'plain@test.local');
    }

    public function test_super_admin_can_create_user_with_role(): void
    {
        $this->actingAs($this->superAdmin);

        $this->postJson('/admin-api/users', [
            'name' => 'New Person',
            'email' => 'new@test.local',
            'password' => 'StrongPass1234!',
            'role' => 'user',
        ])->assertStatus(201)
            ->assertJsonPath('data.name', 'New Person')
            ->assertJsonPath('data.global_role', 'user');

        $user = User::where('email', 'new@test.local')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));

        $this->postJson('/admin-api/users', [
            'name' => 'Second Boss',
            'email' => 'boss2@test.local',
            'password' => 'StrongPass1234!',
            'role' => 'super_admin',
        ])->assertStatus(201);
        $this->assertTrue(User::where('email', 'boss2@test.local')->firstOrFail()->hasRole('super_admin'));
    }

    public function test_create_requires_valid_role(): void
    {
        $this->actingAs($this->superAdmin);

        $this->postJson('/admin-api/users', [
            'name' => 'Bad',
            'email' => 'bad@test.local',
            'password' => 'StrongPass1234!',
            'role' => 'owner',
        ])->assertStatus(422);
    }

    public function test_super_admin_can_update_user(): void
    {
        $this->actingAs($this->superAdmin);

        $this->postJson("/admin-api/users/{$this->plainUser->id}", [
            'name' => 'Renamed',
            'email' => 'renamed@test.local',
            'role' => 'super_admin',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.global_role', 'super_admin');

        $user = $this->plainUser->fresh();
        $this->assertSame('renamed@test.local', $user->email);
        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertFalse($user->hasRole('user'));
    }

    public function test_update_password_optional(): void
    {
        $this->actingAs($this->superAdmin);

        // Without password: unchanged.
        $this->postJson("/admin-api/users/{$this->plainUser->id}", [
            'name' => 'Plain',
            'email' => 'plain@test.local',
            'role' => 'user',
        ])->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('secret', $this->plainUser->fresh()->password));

        // With password: changed.
        $this->postJson("/admin-api/users/{$this->plainUser->id}", [
            'name' => 'Plain',
            'email' => 'plain@test.local',
            'password' => 'StrongPass1234!',
            'password_confirmation' => 'StrongPass1234!',
            'role' => 'user',
        ])->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('StrongPass1234!', $this->plainUser->fresh()->password));
    }

    public function test_cannot_delete_self(): void
    {
        $this->actingAs($this->superAdmin);

        $this->deleteJson("/admin-api/users/{$this->superAdmin->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot delete your own account.');

        $this->assertDatabaseHas('users', ['id' => $this->superAdmin->id]);
    }

    public function test_cannot_delete_last_super_admin(): void
    {
        $this->actingAs($this->superAdmin);

        // A second super admin exists → deletion is allowed.
        $other = User::create(['name' => 'Other Boss', 'email' => 'other@test.local', 'password' => bcrypt('secret')]);
        $other->assignRole('super_admin');

        $this->deleteJson("/admin-api/users/{$other->id}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $other->id]);

        // Now only one super admin remains → cannot be deleted nor demoted.
        $this->deleteJson("/admin-api/users/{$this->superAdmin->id}")->assertStatus(422);
        $this->postJson("/admin-api/users/{$this->superAdmin->id}", [
            'name' => 'Boss',
            'email' => 'boss@test.local',
            'role' => 'user',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Cannot remove the last super admin.');
    }

    public function test_super_admin_can_delete_plain_user(): void
    {
        $this->actingAs($this->superAdmin);

        $this->deleteJson("/admin-api/users/{$this->plainUser->id}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $this->plainUser->id]);
    }

    public function test_index_role_filter_and_counts(): void
    {
        $this->actingAs($this->superAdmin);

        $this->getJson('/admin-api/users')
            ->assertOk()
            ->assertJsonPath('data.counts.all', 2)
            ->assertJsonPath('data.counts.super_admin', 1)
            ->assertJsonPath('data.counts.user', 1);

        $this->getJson('/admin-api/users?role=super_admin')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $this->superAdmin->id);

        $this->getJson('/admin-api/users?role=user')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $this->plainUser->id);
    }

    public function test_bulk_change_role(): void
    {
        $extra = User::create(['name' => 'Extra', 'email' => 'extra@test.local', 'password' => bcrypt('secret')]);
        $extra->assignRole('user');

        $this->actingAs($this->superAdmin);

        $this->postJson('/admin-api/users/bulk', [
            'ids' => [$this->plainUser->id, $extra->id],
            'action' => 'role',
            'role' => 'super_admin',
        ])->assertOk()
            ->assertJsonPath('counts.super_admin', 3);

        $this->assertTrue($this->plainUser->fresh()->hasRole('super_admin'));
        $this->assertTrue($extra->fresh()->hasRole('super_admin'));

        // Back to user.
        $this->postJson('/admin-api/users/bulk', [
            'ids' => [$this->plainUser->id, $extra->id],
            'action' => 'role',
            'role' => 'user',
        ])->assertOk();
        $this->assertFalse($this->plainUser->fresh()->hasRole('super_admin'));
    }

    public function test_bulk_delete(): void
    {
        $extra = User::create(['name' => 'Extra', 'email' => 'extra@test.local', 'password' => bcrypt('secret')]);
        $extra->assignRole('user');

        $this->actingAs($this->superAdmin);

        $this->postJson('/admin-api/users/bulk', [
            'ids' => [$this->plainUser->id, $extra->id],
            'action' => 'delete',
        ])->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $this->plainUser->id]);
        $this->assertDatabaseMissing('users', ['id' => $extra->id]);
    }

    public function test_bulk_cannot_include_self(): void
    {
        $this->actingAs($this->superAdmin);

        $this->postJson('/admin-api/users/bulk', [
            'ids' => [$this->superAdmin->id, $this->plainUser->id],
            'action' => 'delete',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Bulk actions cannot include your own account.');

        $this->assertDatabaseHas('users', ['id' => $this->plainUser->id]);
    }

    public function test_bulk_cannot_remove_last_super_admin(): void
    {
        $this->actingAs($this->superAdmin);

        // Second super admin exists → bulk demote works.
        $other = User::create(['name' => 'Other Boss', 'email' => 'other@test.local', 'password' => bcrypt('secret')]);
        $other->assignRole('super_admin');

        $this->postJson('/admin-api/users/bulk', [
            'ids' => [$other->id],
            'action' => 'role',
            'role' => 'user',
        ])->assertOk();
        $this->assertFalse($other->fresh()->hasRole('super_admin'));

        // Back to super admin.
        $other->assignRole('super_admin');

        // Self is always excluded from bulk, so at least one super admin
        // (the acting user) survives: deleting the other super admin is allowed.
        $this->postJson('/admin-api/users/bulk', [
            'ids' => [$other->id, $this->plainUser->id],
            'action' => 'delete',
        ])->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $other->id]);
    }

    public function test_admin_2fa_endpoints_require_super_admin(): void
    {
        $this->actingAs($this->plainUser);

        $this->postJson("/admin-api/users/{$this->plainUser->id}/2fa/enable")->assertStatus(403);
        $this->postJson("/admin-api/users/{$this->plainUser->id}/2fa/confirm", ['code' => '000000'])->assertStatus(403);
        $this->postJson("/admin-api/users/{$this->plainUser->id}/2fa/disable")->assertStatus(403);
        $this->postJson("/admin-api/users/{$this->plainUser->id}/2fa/recovery-codes")->assertStatus(403);
    }

    public function test_admin_can_enable_confirm_and_disable_2fa_for_user(): void
    {
        $this->actingAs($this->superAdmin);
        $target = $this->plainUser;

        // Enable: returns secret + provisioning uri.
        $this->postJson("/admin-api/users/{$target->id}/2fa/enable")
            ->assertOk()
            ->assertJsonStructure(['secret', 'provisioning_uri']);
        $secret = $target->fresh()->two_factor_secret;
        $this->assertNotEmpty($secret);
        $this->assertFalse($target->fresh()->twoFactorEnabled());

        // Wrong code rejected.
        $this->postJson("/admin-api/users/{$target->id}/2fa/confirm", ['code' => '000000'])
            ->assertStatus(422);

        // Correct TOTP code confirms.
        $code = TwoFactor::codeAt($secret, (int) floor(time() / 30));
        $this->postJson("/admin-api/users/{$target->id}/2fa/confirm", ['code' => $code])
            ->assertOk()
            ->assertJsonStructure(['recovery_codes']);
        $this->assertTrue($target->fresh()->twoFactorEnabled());

        // Disable.
        $this->postJson("/admin-api/users/{$target->id}/2fa/disable")->assertOk();
        $this->assertFalse($target->fresh()->twoFactorEnabled());
    }

    public function test_admin_2fa_disable_and_recovery_require_enabled(): void
    {
        $this->actingAs($this->superAdmin);
        $target = $this->plainUser;

        $this->postJson("/admin-api/users/{$target->id}/2fa/disable")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Two factor authentication is not enabled.');
        $this->postJson("/admin-api/users/{$target->id}/2fa/recovery-codes")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Two factor authentication is not enabled.');

        // Enable fully, then recovery codes can be regenerated.
        $this->postJson("/admin-api/users/{$target->id}/2fa/enable")->assertOk();
        $secret = $target->fresh()->two_factor_secret;
        $code = TwoFactor::codeAt($secret, (int) floor(time() / 30));
        $this->postJson("/admin-api/users/{$target->id}/2fa/confirm", ['code' => $code])->assertOk();

        $this->postJson("/admin-api/users/{$target->id}/2fa/recovery-codes")
            ->assertOk()
            ->assertJsonStructure(['recovery_codes']);
    }

    public function test_update_password_requires_confirmation(): void
    {
        $this->actingAs($this->superAdmin);

        $this->postJson("/admin-api/users/{$this->plainUser->id}", [
            'name' => 'Plain',
            'email' => 'plain@test.local',
            'password' => 'StrongPass1234!',
            'password_confirmation' => 'different',
            'role' => 'user',
        ])->assertStatus(422);

        $this->postJson("/admin-api/users/{$this->plainUser->id}", [
            'name' => 'Plain',
            'email' => 'plain@test.local',
            'password' => 'StrongPass1234!',
            'password_confirmation' => 'StrongPass1234!',
            'role' => 'user',
        ])->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('StrongPass1234!', $this->plainUser->fresh()->password));
    }
}
