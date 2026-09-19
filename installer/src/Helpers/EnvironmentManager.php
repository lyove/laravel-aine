<?php

namespace Aine\Installer\Helpers;

use Exception;
use Illuminate\Http\Request;

class EnvironmentManager
{
    /**
     * @var string
     */
    private $envPath;

    /**
     * @var string
     */
    private $envExamplePath;

    /**
     * Set the .env and .env.example paths.
     */
    public function __construct()
    {
        $this->envPath = base_path('.env');
        $this->envExamplePath = base_path('.env.example');
    }

    /**
     * Get the content of the .env file.
     *
     * @return string
     */
    public function getEnvContent()
    {
        if (!file_exists($this->envPath)) {
            if (file_exists($this->envExamplePath)) {
                copy($this->envExamplePath, $this->envPath);
            } else {
                touch($this->envPath);
            }
        }

        // The installer itself runs through the `web` middleware group, which
        // encrypts cookies/session — that requires a valid APP_KEY. On a
        // fresh deployment the .env (just copied from .env.example) has an
        // empty APP_KEY, so generate one right away. bootstrap/app.php does
        // the same before the framework boots, so this is just a fallback.
        $content = file_get_contents($this->envPath);
        if ($content !== false && !preg_match('/^APP_KEY=.+/m', $content)) {
            $key = 'base64:'.base64_encode(random_bytes(32));
            if (preg_match('/^APP_KEY=.*$/m', $content)) {
                $content = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$key, $content);
            } else {
                $content = rtrim($content, "\r\n")."\nAPP_KEY=".$key."\n";
            }
            file_put_contents($this->envPath, $content);
            $content = file_get_contents($this->envPath);
        }

        return $content;
    }

    /**
     * Get the the .env file path.
     *
     * @return string
     */
    public function getEnvPath()
    {
        return $this->envPath;
    }

    /**
     * Get the the .env.example file path.
     *
     * @return string
     */
    public function getEnvExamplePath()
    {
        return $this->envExamplePath;
    }

    /**
     * Save the edited content to the .env file.
     *
     * @param Request $input
     * @return string
     */
    public function saveFileClassic(Request $input)
    {
        $message = trans('installer_messages.environment.success');

        $allowedKeys = [
            'APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_KEY',
            'LOG_CHANNEL', 'LOG_LEVEL',
            'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
            'BROADCAST_DRIVER', 'CACHE_DRIVER', 'FILESYSTEM_DISK', 'QUEUE_CONNECTION',
            'SESSION_DRIVER', 'SESSION_LIFETIME',
            'MEMCACHED_HOST', 'REDIS_HOST', 'REDIS_PASSWORD', 'REDIS_PORT',
            'MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD',
            'MAIL_ENCRYPTION', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
            'PUSHER_APP_ID', 'PUSHER_APP_KEY', 'PUSHER_APP_SECRET', 'PUSHER_APP_CLUSTER',
            'ADMIN_EMAIL',
        ];

        try {
            $inputLines = preg_split('/\r\n|\r|\n/', (string) $input->get('envConfig'));
            $existing = file_exists($this->envPath) ? file_get_contents($this->envPath) : '';

            $currentKey = '';
            if (preg_match('/^APP_KEY=(.*)$/m', $existing, $m)) {
                $currentKey = trim(trim($m[1]), "\"'");
            }

            $output = [];
            $seenKeys = [];
            foreach ($inputLines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (! preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $m)) {
                    continue;
                }
                $key = $m[1];
                if (! in_array($key, $allowedKeys, true)) {
                    continue;
                }
                if ($key === 'APP_KEY' && $currentKey !== '') {
                    continue; // keep the existing key
                }
                if (isset($seenKeys[$key])) {
                    continue;
                }
                $output[] = $line;
                $seenKeys[$key] = true;
            }

            // Re-insert preserved APP_KEY if it was dropped.
            if ($currentKey !== '' && ! isset($seenKeys['APP_KEY'])) {
                $output[] = 'APP_KEY=' . $currentKey;
            }

            file_put_contents($this->envPath, implode("\n", $output) . "\n");
        } catch (Exception $e) {
            $message = trans('installer_messages.environment.errors');
        }

        return $message;
    }

    /**
     * Save the wizard form to the .env file.
     *
     * @param Request $request
     * @return string
     */
    public function saveFileWizard(Request $request)
    {
        $results = trans('installer_messages.environment.success');

        try {
            $template = file_exists($this->envExamplePath)
                ? file_get_contents($this->envExamplePath)
                : '';

            $map = [
                'APP_NAME'             => $request->input('app_name'),
                'APP_ENV'              => $request->input('environment') === 'other'
                                            ? $request->input('environment_custom')
                                            : $request->input('environment'),
                'APP_DEBUG'            => $request->input('app_debug'),
                'APP_URL'              => $request->input('app_url'),
                'LOG_CHANNEL'          => $request->input('log_channel'),
                'LOG_LEVEL'            => $request->input('log_level'),
                'DB_CONNECTION'        => $request->input('database_connection'),
                'DB_HOST'              => $request->input('database_hostname'),
                'DB_PORT'              => $request->input('database_port'),
                'DB_DATABASE'          => $request->input('database_name'),
                'DB_USERNAME'          => $request->input('database_username'),
                'DB_PASSWORD'          => $request->input('database_password'),
                'BROADCAST_DRIVER'     => $request->input('broadcast_driver'),
                'CACHE_DRIVER'         => $request->input('cache_driver'),
                'FILESYSTEM_DISK'      => $request->input('filesystem_driver'),
                'QUEUE_CONNECTION'     => $request->input('queue_connection'),
                'SESSION_DRIVER'       => $request->input('session_driver'),
                'SESSION_LIFETIME'     => $request->input('session_lifetime'),
                'MEMCACHED_HOST'       => $request->input('memcache_hostname'),
                'REDIS_HOST'           => $request->input('redis_hostname'),
                'REDIS_PASSWORD'       => $request->input('redis_password'),
                'REDIS_PORT'           => $request->input('redis_port'),
                'MAIL_MAILER'          => $request->input('mail_mailer'),
                'MAIL_HOST'            => $request->input('mail_host'),
                'MAIL_PORT'            => $request->input('mail_port'),
                'MAIL_USERNAME'        => $request->input('mail_username'),
                'MAIL_PASSWORD'        => $request->input('mail_password'),
                'MAIL_ENCRYPTION'      => $request->input('mail_encryption'),
                'MAIL_FROM_ADDRESS'    => $request->input('mail_from_address'),
                'MAIL_FROM_NAME'       => $request->input('mail_from_name'),
                'PUSHER_APP_ID'        => $request->input('pusher_app_id'),
                'PUSHER_APP_KEY'       => $request->input('pusher_app_key'),
                'PUSHER_APP_SECRET'    => $request->input('pusher_app_secret'),
                'PUSHER_APP_CLUSTER'   => $request->input('pusher_app_cluster'),
            ];

            // SQLite needs no server credentials; keep the user-provided
            // database name (a path) if any, otherwise blank = default file.
            // Relative paths are resolved against the project root so the
            // PDO connection works regardless of the working directory.
            if ($request->input('database_connection') === 'sqlite') {
                $map['DB_HOST'] = '';
                $map['DB_PORT'] = '';
                $map['DB_USERNAME'] = '';
                $map['DB_PASSWORD'] = '';
                $dbName = $request->input('database_name');
                if ($dbName && ! str_starts_with($dbName, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $dbName)) {
                    $dbName = base_path($dbName);
                }
                $map['DB_DATABASE'] = $dbName ?: database_path('database.sqlite');
            }

            $current = file_exists($this->envPath) ? file_get_contents($this->envPath) : '';
            if (preg_match('/^APP_KEY=(.*)$/m', (string) $current, $m)) {
                $map['APP_KEY'] = trim(trim($m[1]), '"\'');
            }

            $lines = preg_split('/\r\n|\r|\n/', (string) $template);
            $output = [];
            $replaced = [];

            foreach ($lines as $line) {
                $matched = false;
                foreach ($map as $key => $value) {
                    if ($value === null) {
                        continue;
                    }
                    if (preg_match('/^'.preg_quote($key, '/').'=/', $line)) {
                        $output[] = $key.'='.$this->formatEnvValue($value);
                        $replaced[$key] = true;
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    $output[] = $line;
                }
            }

            // Append any mapped keys missing from the template.
            foreach ($map as $key => $value) {
                if ($value === null || isset($replaced[$key])) {
                    continue;
                }
                $output[] = $key.'='.$this->formatEnvValue($value);
            }

            $adminEmail = (string) $request->input('admin_email');
            if ($adminEmail !== '') {
                $output = array_values(array_filter(
                    $output,
                    fn ($line) => ! preg_match('/^ADMIN_EMAIL=/', $line)
                ));
                $output[] = 'ADMIN_EMAIL='.$this->formatEnvValue($adminEmail);
            }

            file_put_contents($this->envPath, implode("\n", $output)."\n");
        } catch (Exception $e) {
            $results = trans('installer_messages.environment.errors');
        }

        return $results;
    }

    /**
     * Quote env values that contain whitespace or special characters.
     *
     * @param mixed $value
     * @return string
     */
    private function formatEnvValue($value)
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        if (preg_match('/[\s"\'#\$]/', $value)) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }
}
