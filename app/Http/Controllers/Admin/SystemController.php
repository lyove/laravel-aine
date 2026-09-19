<?php

namespace App\Http\Controllers\Admin;

use App\Aine\AuditLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SystemController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | System status (read-only)
    |--------------------------------------------------------------------------
    */
    public function status(): JsonResponse
    {
        $dbName = config('database.default');
        $dbConnection = config("database.connections.{$dbName}.database", '—');

        // Disk space on the project root
        $diskFree = @disk_free_space(base_path());
        $diskTotal = @disk_total_space(base_path());

        // Failed queue jobs
        $failedJobs = 0;
        try {
            $failedJobs = DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {
            // table might not exist
        }

        return response()->json([
            'php_version'       => PHP_VERSION,
            'laravel_version'   => app()->version(),
            'environment'       => app()->environment(),
            'debug'             => config('app.debug'),
            'maintenance'       => app()->isDownForMaintenance(),
            'database'          => [
                'driver'   => $dbName,
                'database' => $dbConnection,
                'connected' => $this->checkDatabaseConnection(),
            ],
            'disk' => [
                'free'  => $this->formatBytes($diskFree),
                'total' => $this->formatBytes($diskTotal),
                'used'  => $this->formatBytes($diskTotal - $diskFree),
            ],
            'failed_jobs'       => $failedJobs,
            'php_extensions'    => array_slice(get_loaded_extensions(), 0, 20),
            'timezone'          => config('app.timezone'),
            'queue_connection'  => config('queue.default'),
            'cache_driver'      => config('cache.default'),
            'session_driver'    => config('session.driver'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Cache management
    |--------------------------------------------------------------------------
    */
    public function clearAllCache(): JsonResponse
    {
        Artisan::call('optimize:clear');
        AuditLogger::log('system', 'cache', null, 'Cleared all caches (optimize:clear)');

        return $this->okResponse('All caches cleared.', Artisan::output());
    }

    public function clearRouteCache(): JsonResponse
    {
        Artisan::call('route:clear');
        AuditLogger::log('system', 'cache', null, 'Cleared route cache');

        return $this->okResponse('Route cache cleared.', Artisan::output());
    }

    public function clearConfigCache(): JsonResponse
    {
        Artisan::call('config:clear');
        AuditLogger::log('system', 'cache', null, 'Cleared config cache');

        return $this->okResponse('Config cache cleared.', Artisan::output());
    }

    public function clearViewCache(): JsonResponse
    {
        Artisan::call('view:clear');
        AuditLogger::log('system', 'cache', null, 'Cleared view cache');

        return $this->okResponse('View cache cleared.', Artisan::output());
    }

    public function clearAppCache(): JsonResponse
    {
        Artisan::call('cache:clear');
        AuditLogger::log('system', 'cache', null, 'Cleared application cache');

        return $this->okResponse('Application cache cleared.', Artisan::output());
    }

    public function rebuildCache(): JsonResponse
    {
        $output = '';
        Artisan::call('config:cache');
        $output .= Artisan::output();
        Artisan::call('route:cache');
        $output .= Artisan::output();
        AuditLogger::log('system', 'cache', null, 'Rebuilt config & route cache');

        return $this->okResponse('Config and route cache rebuilt.', $output);
    }

    /*
    |--------------------------------------------------------------------------
    | Storage & logs
    |--------------------------------------------------------------------------
    */
    public function storageLink(): JsonResponse
    {
        try {
            $target = storage_path('app/public');
            $link = public_path('storage');

            // Remove a dangling symlink (target missing) so it can be recreated.
            if (is_link($link) && ! file_exists($link)) {
                @unlink($link);
            }

            // Already linked and valid — nothing to do.
            if (is_link($link) && file_exists($link)) {
                AuditLogger::log('system', 'storage', null, 'Storage link already exists');

                return $this->okResponse('Storage link already exists.');
            }

            // A real file or folder occupies the target path.
            if (file_exists($link)) {
                return $this->errorResponse('public/storage already exists as a file or folder. Move it away first, then try again.');
            }

            // Build the link with PHP's native symlink() so this works even on
            // servers where exec() is disabled (artisan storage:link would
            // otherwise fall back to `ln -s` and fail).
            if (! function_exists('symlink')) {
                return $this->errorResponse(
                    'PHP symlink() is disabled on this server (disable_functions). Enable it in php.ini, or create the link manually from the terminal: ln -s '.$target.' '.$link
                );
            }

            if (! @symlink($target, $link)) {
                $err = error_get_last();

                return $this->errorResponse('Failed to create storage link: '.($err['message'] ?? 'symlink() failed. Check directory permissions.'));
            }

            AuditLogger::log('system', 'storage', null, 'Recreated storage link');

            return $this->okResponse('Storage link created.');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to create storage link: '.$e->getMessage());
        }
    }

    public function clearLogs(): JsonResponse
    {
        $logPath = storage_path('logs');
        $files   = glob($logPath . '/*.log');
        $deleted = 0;

        foreach ($files as $file) {
            // Skip the current day's log
            if (str_contains(basename($file), now()->format('Y-m-d'))) {
                continue;
            }
            if (@unlink($file)) {
                $deleted++;
            }
        }

        AuditLogger::log('system', 'storage', null, "Cleared {$deleted} old log file(s)");

        return $this->okResponse("{$deleted} old log file(s) cleared.");
    }

    /*
    |--------------------------------------------------------------------------
    | Maintenance mode
    |--------------------------------------------------------------------------
    */
    public function maintenanceDown(): JsonResponse
    {
        Artisan::call('down', ['--render' => 'errors::503']);
        AuditLogger::log('system', 'maintenance', null, 'Enabled maintenance mode');

        return $this->okResponse('Maintenance mode enabled.');
    }

    public function maintenanceUp(): JsonResponse
    {
        Artisan::call('up');
        AuditLogger::log('system', 'maintenance', null, 'Disabled maintenance mode');

        return $this->okResponse('Maintenance mode disabled.');
    }

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    public function migrateStatus(): JsonResponse
    {
        Artisan::call('migrate:status');
        $output = Artisan::output();

        $pending = substr_count($output, '| No    ');
        $ran     = substr_count($output, '| Yes   ');

        return response()->json([
            'success' => true,
            'message' => "{$ran} ran, {$pending} pending",
            'output'  => $output,
            'ran'     => $ran,
            'pending' => $pending,
        ]);
    }

    public function runMigrations(): JsonResponse
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            AuditLogger::log('system', 'database', null, 'Ran pending migrations');

            return $this->okResponse('Migrations executed.', Artisan::output());
        } catch (\Throwable $e) {
            return $this->errorResponse('Migration failed: ' . $e->getMessage());
        }
    }

    public function freshSeed(): JsonResponse
    {
        if (app()->environment('production')) {
            return $this->errorResponse('This operation is not allowed in production.');
        }

        try {
            Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
            AuditLogger::log('system', 'database', null, 'Database fresh + seed (non-production)');

            return $this->okResponse('Database reset and seeded.', Artisan::output());
        } catch (\Throwable $e) {
            return $this->errorResponse('Fresh + seed failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    private function checkDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function okResponse(string $message, string $output = ''): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'output'  => trim($output),
        ]);
    }

    private function errorResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 422);
    }

    private function formatBytes($bytes): string
    {
        if ($bytes === false) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i     = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }
}
