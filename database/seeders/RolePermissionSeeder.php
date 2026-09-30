<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Idempotent role/permission seeding for the global (web guard) roles.
 *
 * Roles:
 *   super_admin — intentionally holds NO permissions; Gate::before grants
 *                 full access (see AuthServiceProvider). Never weaken it.
 *   editor      — content editor: manage any content incl. publish, moderate
 *                 comments, manage media, run workflow transitions.
 *   user        — frontend persona: comment, favorite, like, edit own profile.
 *
 * `super_admin` / `user` are created by DatabaseSeeder; this seeder only
 * creates missing permissions, the `editor` role, and assigns permissions.
 */
class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        $all = [
            // Editor — content lifecycle & moderation
            'content:read',
            'content:create',
            'content:update',
            'content:delete',
            'content:publish',
            'content:unpublish',
            'comments:read',
            'comments:approve',
            'comments:reject',
            'comments:mark_spam',
            'media:read',
            'media:upload',
            'media:delete',
            'workflow:transition',
            'workflow:approve',

            // Frontend user — interactions & own profile
            'comments:create',
            'favorites:create',
            'favorites:delete',
            'likes:create',
            'likes:delete',
            'profile:edit_own',
        ];

        foreach ($all as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $editor = Role::firstOrCreate(['name' => 'editor']);
        $editor->syncPermissions([
            'content:read',
            'content:create',
            'content:update',
            'content:delete',
            'content:publish',
            'content:unpublish',
            'comments:read',
            'comments:approve',
            'comments:reject',
            'comments:mark_spam',
            'media:read',
            'media:upload',
            'media:delete',
            'workflow:transition',
            'workflow:approve',
        ]);

        $user = Role::firstOrCreate(['name' => 'user']);
        $user->syncPermissions([
            'comments:create',
            'favorites:create',
            'favorites:delete',
            'likes:create',
            'likes:delete',
            'profile:edit_own',
        ]);
    }
}
