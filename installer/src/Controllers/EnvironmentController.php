<?php

namespace Aine\Installer\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Aine\Installer\Events\EnvironmentSaved;
use Aine\Installer\Helpers\EnvironmentManager;

class EnvironmentController extends Controller
{
    /**
     * @var EnvironmentManager
     */
    protected $EnvironmentManager;

    /**
     * @param EnvironmentManager $environmentManager
     */
    public function __construct(EnvironmentManager $environmentManager)
    {
        $this->EnvironmentManager = $environmentManager;
    }

    /**
     * Display the Environment menu page.
     *
     * @return \Illuminate\View\View
     */
    public function environmentMenu()
    {
        return view('vendor.installer.environment');
    }

    /**
     * Display the Environment page.
     *
     * @return \Illuminate\View\View
     */
    public function environmentWizard()
    {
        $envConfig = $this->EnvironmentManager->getEnvContent();

        return view('vendor.installer.environment-wizard', compact('envConfig'));
    }

    /**
     * Display the Environment page.
     *
     * @return \Illuminate\View\View
     */
    public function environmentClassic()
    {
        $envConfig = $this->EnvironmentManager->getEnvContent();

        return view('vendor.installer.environment-classic', compact('envConfig'));
    }

    /**
     * Processes the newly saved environment configuration (Classic).
     *
     * @param Request $input
     * @param Redirector $redirect
     * @return \Illuminate\Http\RedirectResponse
     */
    public function saveClassic(Request $input, Redirector $redirect)
    {
        $message = $this->EnvironmentManager->saveFileClassic($input);

        event(new EnvironmentSaved($input));

        return $redirect->route('LaravelInstaller::environmentClassic')
                        ->with(['message' => $message]);
    }

    /**
     * Processes the newly saved environment configuration (Form Wizard).
     *
     * @param Request $request
     * @param Redirector $redirect
     * @return \Illuminate\Http\RedirectResponse
     */
    public function saveWizard(Request $request, Redirector $redirect)
    {
        $rules = config('installer.environment.form.rules');
        $messages = [
            'environment_custom.required_if' => trans('installer_messages.environment.wizard.form.name_required'),
        ];

        if ($request->input('database_connection') === 'sqlite') {
            foreach (['database_hostname', 'database_port', 'database_name', 'database_username'] as $key) {
                unset($rules[$key]);
            }
        }

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return $redirect->route('LaravelInstaller::environmentWizard')->withInput()->withErrors($validator->errors());
        }

        if (! $this->checkDatabaseConnection($request)) {
            return $redirect->route('LaravelInstaller::environmentWizard')->withInput()->withErrors([
                'database_connection' => trans('installer_messages.environment.wizard.form.db_connection_failed'),
            ]);
        }

        $results = $this->EnvironmentManager->saveFileWizard($request);

        $dbName = $request->input('database_name');
        if ($request->input('database_connection') === 'sqlite' && ! $dbName) {
            $dbName = 'database/database.sqlite (default)';
        }
        session([
            'installer_admin' => [
                'name' => $request->input('admin_name'),
                'email' => $request->input('admin_email'),
                'password' => $request->input('admin_password'),
            ],
            'installer_summary' => [
                'app_name' => $request->input('app_name'),
                'environment' => $request->input('environment'),
                'app_debug' => $request->input('app_debug'),
                'app_url' => $request->input('app_url'),
                'database_connection' => $request->input('database_connection'),
                'database_name' => $dbName,
                'database_host' => $request->input('database_hostname'),
                'database_port' => $request->input('database_port'),
                'database_username' => $request->input('database_username'),
                'admin_name' => $request->input('admin_name'),
                'admin_email' => $request->input('admin_email'),
                'log_level' => $request->input('log_level'),
                'cache_driver' => $request->input('cache_driver'),
                'session_driver' => $request->input('session_driver'),
                'mail_mailer' => $request->input('mail_mailer'),
                'mail_from_address' => $request->input('mail_from_address'),
            ],
        ]);

        event(new EnvironmentSaved($request));

        return redirect($request->getSchemeAndHttpHost().route('LaravelInstaller::confirm', [], false));
    }

    /**
     * Validate database connection with user credentials (Form Wizard).
     *
     * @param Request $request
     * @return bool
     */
    private function checkDatabaseConnection(Request $request)
    {
        $connection = $request->input('database_connection');

        if ($connection === 'sqlite') {
            return true;
        }

        $settings = config("database.connections.$connection");

        if (! is_array($settings)) {
            return false;
        }

        config([
            'database' => [
                'default' => $connection,
                'connections' => [
                    $connection => array_merge($settings, [
                        'driver' => $connection,
                        'host' => $request->input('database_hostname'),
                        'port' => $request->input('database_port'),
                        'database' => $request->input('database_name'),
                        'username' => $request->input('database_username'),
                        'password' => $request->input('database_password'),
                    ]),
                ],
            ],
        ]);

        DB::purge();

        try {
            DB::connection()->getPdo();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
