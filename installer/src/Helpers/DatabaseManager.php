<?php

namespace Aine\Installer\Helpers;

use App\Models\User;
use Exception;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Output\BufferedOutput;

class DatabaseManager
{
    /**
     * Migrate and seed the database.
     *
     * @param  array|null  $admin  Admin credentials from the wizard form
     * @return array
     */
    public function migrateAndSeed(?array $admin = null)
    {
        $outputLog = new BufferedOutput;

        try {
            $this->sqlite($outputLog);
        } catch (\Throwable $e) {
            return $this->response($e->getMessage(), 'error', $outputLog);
        }

        return $this->migrate($outputLog, $admin);
    }

    /**
     * Run the migration and call the seeder.
     *
     * @param \Symfony\Component\Console\Output\BufferedOutput $outputLog
     * @param array|null  $admin  Admin credentials from the wizard form
     * @return array
     */
    private function migrate(BufferedOutput $outputLog, ?array $admin = null)
    {
        try {
            Artisan::call('migrate', ['--force'=> true], $outputLog);

            try {
                Artisan::call('storage:Link');
            } catch (\Throwable $e) {
                // handled below via the existence check
            }

            $target = storage_path('app/public');
            $link = public_path('storage');
            // Remove a dangling symlink (target missing) so it can be recreated.
            if (is_link($link) && ! file_exists($link)) {
                @unlink($link);
            }
            if (! file_exists($link) && function_exists('symlink')) {
                try {
                    @symlink($target, $link);
                } catch (\Throwable $e2) {
                    // ignore; the fallback route covers this
                }
            }
            if (! file_exists($link) && ! is_link($link)) {
                $outputLog->write('storage:link skipped: public/storage symlink could not be created — the /storage fallback route will serve media files.', 1);
            }

            if ($admin && ! empty($admin['email'])) {
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
            }

            $other_commands = config('installer.artisan_command');
            if (!empty($other_commands)) {
                config(['installer.seed_demo_skip_admin' => true]);
                foreach ($other_commands as $key => $value) {
                    Artisan::call($key, $value, $outputLog);
                }
            }

            $publicRoot = storage_path('app/public');
            if (is_dir($publicRoot)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($publicRoot, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $item) {
                    @chmod($item->getPathname(), $item->isDir() ? 0775 : 0664);
                }
            }
        } catch (\Throwable $e) {
            return $this->response($e->getMessage(), 'error', $outputLog);
        }

        return $this->response(trans('installer_messages.final.finished'), 'success', $outputLog);
    }

    /**
     * Return a formatted error messages.
     *
     * @param string $message
     * @param string $status
     * @param \Symfony\Component\Console\Output\BufferedOutput $outputLog
     * @return array
     */
    private function response($message, $status, BufferedOutput $outputLog)
    {
        return [
            'status' => $status,
            'message' => $message,
            'dbOutputLog' => $outputLog->fetch(),
        ];
    }

    /**
     * Check database type. If SQLite, then create the database file.
     *
     * @param \Symfony\Component\Console\Output\BufferedOutput $outputLog
     */
    private function sqlite(BufferedOutput $outputLog)
    {
        if (DB::connection() instanceof SQLiteConnection) {
            $database = DB::connection()->getDatabaseName();
            if ($database && ! str_starts_with($database, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $database)) {
                $database = base_path($database);
            }
            if (! file_exists($database)) {
                $dir = dirname($database);
                if (! is_dir($dir)) {
                    if (! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                        throw new \RuntimeException(
                            "Unable to create the SQLite database directory \"{$dir}\". "
                            . 'Please ensure the web/PHP user has write permission '
                            . 'on the parent directory, then retry the installation. '
                            . '(Hint: chown -R www-data:www-data database/ && chmod -R 775 database/)'
                        );
                    }
                }
                if (! @touch($database)) {
                    $realDir = dirname($database);
                    throw new \RuntimeException(
                        "Unable to create the SQLite database file \"{$database}\". "
                        . "The directory \"{$realDir}\" exists but is not writable by the web/PHP user. "
                        . '(Hint: chown -R www-data:www-data database/ && chmod -R 775 database/)'
                    );
                }
                DB::reconnect(Config::get('database.default'));
            }
            $outputLog->write('Using SqlLite database: '.$database, 1);
        }
    }
}