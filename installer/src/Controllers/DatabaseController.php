<?php

namespace Aine\Installer\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Routing\Controller;
use Aine\Installer\Helpers\DatabaseManager;
use Spatie\Permission\Models\Role;

class DatabaseController extends Controller
{
    /**
     * @var DatabaseManager
     */
    private $databaseManager;

    /**
     * @param DatabaseManager $databaseManager
     */
    public function __construct(DatabaseManager $databaseManager)
    {
        $this->databaseManager = $databaseManager;
    }

    /**
     * Migrate and seed the database, then create the admin account
     * passed through the session by the wizard.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function database(Request $request)
    {
        if (! $request->session()->has('installer_summary')) {
            return redirect()->route('LaravelInstaller::environmentWizard');
        }

        $response = $this->databaseManager->migrateAndSeed();

        if (($response['status'] ?? null) === 'error') {
            return redirect()->route('LaravelInstaller::environment')
                ->with('message', $response)
                ->withInput();
        }

        $admin = $request->session()->pull('installer_admin');

        if (empty($admin['email'])) {
            return redirect()->route('LaravelInstaller::environmentWizard')
                ->withErrors(['admin_email' => trans('installer_messages.confirm.admin_email_missing')]);
        }

        $user = User::firstOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'] ?? '',
                'password' => Hash::make($admin['password']),
            ]
        );

        $user->name = $admin['name'] ?? $user->name;
        $user->password = Hash::make($admin['password']);
        $user->email_verified_at = now();
        $user->save();

        $role = Role::firstOrCreate(['name' => 'super_admin']);
        if (! $user->hasRole('super_admin')) {
            $user->assignRole($role);
        }

        Role::firstOrCreate(['name' => 'user']);

        $setting = Setting::first();
        if (! $setting) {
            Setting::create([
                'name' => config('app.name', 'My Website'),
                'description' => 'My Website Description',
            ]);
        }

        return redirect()->route('LaravelInstaller::final')
                         ->with(['message' => $response]);
    }
}
