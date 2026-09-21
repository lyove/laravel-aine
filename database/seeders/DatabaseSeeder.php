<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Setting;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('admin'),
            ]
        );

        $role = Role::firstOrCreate(['name' => 'super_admin']);

        if (! $user->hasRole('super_admin')) {
            $user->assignRole($role);
        }

        Role::firstOrCreate(['name' => 'user']);

        $this->call(RolePermissionSeeder::class);
        $this->call(ProjectTemplatesSeeder::class);

        Setting::firstOrCreate(
            ['id' => 1],
            [
                'name' => config('app.name', 'My Website'),
                'description' => 'My Website Description',
            ]
        );
    }
}
