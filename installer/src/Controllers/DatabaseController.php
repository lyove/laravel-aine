<?php

namespace Aine\Installer\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Aine\Installer\Helpers\DatabaseManager;

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

        $admin = $request->session()->get('installer_admin');

        if (empty($admin['email'])) {
            return redirect()->route('LaravelInstaller::environmentWizard')
                ->withErrors(['admin_email' => trans('installer_messages.confirm.admin_email_missing')]);
        }

        $response = $this->databaseManager->migrateAndSeed($admin);

        if (($response['status'] ?? null) === 'error') {
            return redirect()->route('LaravelInstaller::environment')
                ->with('message', $response)
                ->withInput();
        }

        $request->session()->pull('installer_admin');

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
