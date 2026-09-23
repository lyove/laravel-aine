<?php

namespace Aine\Installer\Controllers;

use Illuminate\Routing\Controller;
use Aine\Installer\Helpers\DatabaseManager;
use Aine\Installer\Helpers\InstalledFileManager;

class UpdateController extends Controller
{
    use \Aine\Installer\Helpers\MigrationsHelper;

    /**
     * Display the updater welcome page.
     *
     * @return \Illuminate\View\View
     */
    public function welcome()
    {
        return view('vendor.installer.update.welcome');
    }

    /**
     * Display the updater overview page.
     *
     * @return \Illuminate\View\View
     */
    public function overview()
    {
        $migrations = $this->getMigrations();
        $dbMigrations = $this->getExecutedMigrations();

        return view('vendor.installer.update.overview', ['numberOfUpdatesPending' => count($migrations) - count($dbMigrations)]);
    }

    /**
     * Migrate and seed the database.
     *
     * @return \Illuminate\View\View
     */
    public function database()
    {
        $databaseManager = new DatabaseManager;

        config(['installer.artisan_command' => []]);

        $response = $databaseManager->migrateAndSeed();

        if (($response['status'] ?? null) === 'error') {
            return redirect()->route('LaravelUpdater::overview')
                ->with('message', $response);
        }

        return redirect()->route('LaravelUpdater::final')
                         ->with(['message' => $response]);
    }

    /**
     * Update installed file and display finished view.
     *
     * @param InstalledFileManager $fileManager
     * @return \Illuminate\View\View
     */
    public function finish(InstalledFileManager $fileManager)
    {
        if (! filter_var(config('installer.updaterEnabled'), FILTER_VALIDATE_BOOLEAN)) {
            abort(404);
        }

        $fileManager->update();

        return view('vendor.installer.update.finished');
    }
}
