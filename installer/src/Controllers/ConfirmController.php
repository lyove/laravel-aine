<?php

namespace Aine\Installer\Controllers;

use Illuminate\Routing\Controller;

class ConfirmController extends Controller
{
    /**
     * Show a summary of the wizard configuration before the database
     * migration runs, giving the user a last chance to go back.
     *
     * @return \Illuminate\View\View
     */
    public function confirm()
    {
        $summary = session('installer_summary', []);

        if (empty($summary)) {
            $summary = array_filter([
                'app_name' => env('APP_NAME'),
                'environment' => env('APP_ENV'),
                'app_debug' => env('APP_DEBUG'),
                'app_url' => env('APP_URL'),
                'database_connection' => env('DB_CONNECTION'),
                'database_name' => env('DB_DATABASE'),
                'database_host' => env('DB_HOST'),
                'database_port' => env('DB_PORT'),
                'database_username' => env('DB_USERNAME'),
                'admin_name' => session('installer_admin.name') ?: env('ADMIN_NAME'),
                'admin_email' => env('ADMIN_EMAIL') ?: session('installer_admin.email'),
                'log_level' => env('LOG_LEVEL'),
                'cache_driver' => env('CACHE_DRIVER'),
                'session_driver' => env('SESSION_DRIVER'),
                'mail_mailer' => env('MAIL_MAILER'),
                'mail_from_address' => env('MAIL_FROM_ADDRESS'),
            ], fn ($value) => $value !== null && $value !== '');

            if (empty($summary)) {
                return redirect()->route('LaravelInstaller::environmentWizard');
            }
        }

        return view('vendor.installer.confirm-installation', compact('summary'));
    }
}
