<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Global / Shared
    |--------------------------------------------------------------------------
    | Page title and navigation labels shared by every installer screen.
    */

    'title' => 'Aine Installer',
    'next' => 'Next Step',
    'back' => 'Back',
    'finish' => 'Install',
    'install' => 'Install',

    'forms' => [
        'errorTitle' => 'The Following errors occurred:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Step bar
    |--------------------------------------------------------------------------
    | Labels of the progress bar shown at the top of every installer screen,
    | in display order: Welcome → Requirements → Permissions → Configuration
    | → Confirm Installation → Finalize.
    */
    'steps' => [
        'welcome' => 'Welcome',
        'requirements' => 'Requirements',
        'permissions' => 'Permissions',
        'environment' => 'Configuration',
        'confirm' => 'Confirm Installation',
        'database' => 'Database',
        'final' => 'Finalize',
    ],

    /*
    |--------------------------------------------------------------------------
    | Step 1 — Welcome
    |--------------------------------------------------------------------------
    */
    'welcome' => [
        'templateTitle' => 'Welcome',
        'title' => 'Aine Installer',
        'message' => 'Easy Installation and Setup Wizard.',
        'next' => 'Check Requirements',
    ],

    /*
    |--------------------------------------------------------------------------
    | Step 2 — Requirements
    |--------------------------------------------------------------------------
    */
    'requirements' => [
        'templateTitle' => 'Step 2 | Server Requirements',
        'title' => 'Server Requirements',
        'next' => 'Check Permissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Step 3 — Permissions
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'templateTitle' => 'Step 3 | Permissions',
        'title' => 'Permissions',
        'next' => 'Configure Environment',
    ],

    /*
    |--------------------------------------------------------------------------
    | Step 4 — Environment configuration
    |--------------------------------------------------------------------------
    | 4.1 Menu: choose between the guided wizard and the classic editor.
    | 4.2 Wizard: tabbed form (Environment / Other).
    | 4.3 Classic: plain-text .env editor.
    */
    'environment' => [

        /*
         * 4.1 Menu — choose a configuration method.
         */
        'menu' => [
            'templateTitle' => 'Step 4 | Configuration',
            'title' => 'Configuration',
            'desc' => 'Please select how you want to configure the apps <code>.env</code> file.',
            'wizard-button' => 'Form Wizard Setup',
            'classic-button' => 'Classic Text Editor',
        ],

        /*
         * 4.2 Guided wizard — tabbed form.
         */
        'wizard' => [
            'templateTitle' => 'Step 4 | Configuration | Guided Wizard',
            'title' => 'Guided <code>.env</code> Wizard',

            'tabs' => [
                'environment' => 'Environment',
                'database' => 'Database',
                'application' => 'Application',
            ],

            'form' => [

                /*
                 * Environment tab · Application settings.
                 */
                'name_required' => 'An environment name is required.',
                'app_name_label' => 'App Name',
                'app_name_placeholder' => 'App Name',
                'app_environment_label' => 'App Environment',
                'app_environment_label_local' => 'Local',
                'app_environment_label_developement' => 'Development',
                'app_environment_label_qa' => 'Qa',
                'app_environment_label_production' => 'Production',
                'app_environment_label_other' => 'Other',
                'app_environment_placeholder_other' => 'Enter your environment...',
                'app_debug_label' => 'App Debug',
                'app_debug_label_true' => 'True',
                'app_debug_label_false' => 'False',
                'log_level_label' => 'App Log Level',
                'log_level_label_debug' => 'debug',
                'log_level_label_info' => 'info',
                'log_level_label_notice' => 'notice',
                'log_level_label_warning' => 'warning',
                'log_level_label_error' => 'error',
                'log_level_label_critical' => 'critical',
                'log_level_label_alert' => 'alert',
                'log_level_label_emergency' => 'emergency',
                'app_url_label' => 'App Url',
                'app_url_placeholder' => 'App Url',

                /*
                 * Environment tab · Database connection.
                 */
                'db_connection_failed' => 'Could not connect to the database.',
                'db_connection_label' => 'Database Connection',
                'db_connection_label_mysql' => 'mysql',
                'db_connection_label_sqlite' => 'sqlite',
                'db_connection_label_pgsql' => 'pgsql',
                'db_connection_label_sqlsrv' => 'sqlsrv',
                'db_host_label' => 'Database Host',
                'db_host_placeholder' => 'Database Host',
                'db_port_label' => 'Database Port',
                'db_port_placeholder' => 'Database Port',
                'db_name_label' => 'Database Name',
                'db_name_placeholder' => 'Database Name',
                'sqlite_path_placeholder' => '(optional) e.g. database/installer.sqlite - blank uses the default database/database.sqlite',
                'db_username_label' => 'Database User Name',
                'db_username_placeholder' => 'Database User Name',
                'db_password_label' => 'Database Password',
                'db_password_placeholder' => 'Database Password',

                /*
                 * "Other" tab content and the admin account block.
                 */
                'app_tabs' => [

                    /*
                     * Environment tab · Admin account (bottom of the page).
                     */
                    'admin_label' => 'Admin Account',
                    'admin_name_label' => 'Admin Name',
                    'admin_name_placeholder' => 'Admin Name',
                    'admin_email_label' => 'Admin Email',
                    'admin_email_placeholder' => 'admin@example.com',
                    'admin_password_label' => 'Admin Password',
                    'admin_password_placeholder' => 'At least 8 characters',

                    /*
                     * Other tab · Broadcasting / Cache / Session / Queue.
                     */
                    'other_label' => 'Other',
                    'more_info' => 'More Info',
                    'broadcasting_title' => 'Broadcasting, Caching, Session, &amp; Queue',
                    'broadcasting_label' => 'Broadcast Driver',
                    'broadcasting_placeholder' => 'Broadcast Driver',
                    'cache_label' => 'Cache Driver',
                    'cache_placeholder' => 'Cache Driver',
                    'session_label' => 'Session Driver',
                    'session_placeholder' => 'Session Driver',
                    'queue_label' => 'Queue Driver',
                    'queue_placeholder' => 'Queue Driver',

                    /*
                     * Other tab · Redis.
                     */
                    'redis_label' => 'Redis Driver',
                    'redis_host' => 'Redis Host',
                    'redis_host_placeholder' => '127.0.0.1',
                    'redis_password' => 'Redis Password',
                    'redis_password_placeholder' => 'Leave blank if none',
                    'redis_port' => 'Redis Port',
                    'redis_port_placeholder' => '6379',

                    /*
                     * Other tab · Mail (all fields optional).
                     */
                    'mail_label' => 'Mail',
                    'mail_driver_label' => 'Mail Driver',
                    'mail_option_log' => 'Log (no sending)',
                    'mail_option_smtp' => 'SMTP',
                    'mail_option_sendmail' => 'Sendmail',
                    'mail_driver_placeholder' => 'Mail Driver',
                    'mail_host_label' => 'Mail Host',
                    'mail_host_placeholder' => 'Mail Host',
                    'mail_port_label' => 'Mail Port',
                    'mail_port_placeholder' => 'Mail Port',
                    'mail_username_label' => 'Mail Username',
                    'mail_username_placeholder' => 'Mail Username',
                    'mail_password_label' => 'Mail Password',
                    'mail_password_placeholder' => 'Mail Password',
                    'mail_encryption_label' => 'Mail Encryption',
                    'mail_encryption_placeholder' => 'Mail Encryption',
                    'mail_optional_hint' => 'Mail configuration is optional. Leave as "Log" to skip SMTP setup — you can configure it later in .env',
                    'mail_from_address_label' => 'Mail From Address',
                    'mail_from_address_placeholder' => 'noreply@example.com',
                    'mail_from_name_label' => 'Mail From Name',
                    'mail_from_name_placeholder' => 'Aine',

                    /*
                     * Other tab · Pusher.
                     */
                    'pusher_label' => 'Pusher',
                    'pusher_app_id_label' => 'Pusher App Id',
                    'pusher_app_id_placeholder' => 'Pusher App Id',
                    'pusher_app_key_label' => 'Pusher App Key',
                    'pusher_app_key_placeholder' => 'Pusher App Key',
                    'pusher_app_secret_label' => 'Pusher App Secret',
                    'pusher_app_secret_placeholder' => 'Pusher App Secret',
                ],

                'buttons' => [
                    'setup_application' => 'Setup Application',
                    'install' => 'Install',
                ],
            ],
        ],

        /*
         * 4.3 Classic editor — plain-text .env editing.
         */
        'classic' => [
            'templateTitle' => 'Step 4 | Configuration | Classic Editor',
            'title' => 'Classic Configuration Editor',
            'save' => 'Save .env',
            'back' => 'Use Form Wizard',
            'install' => 'Save and Install',
        ],

        'success' => 'Your .env file settings have been saved.',
        'errors' => 'Unable to save the .env file, Please create it manually.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Step 5 — Confirm installation
    |--------------------------------------------------------------------------
    | Keys are ordered to match the confirmation page layout:
    | Key settings → Other settings → warning → bottom actions.
    */
    'confirm' => [
        'templateTitle' => 'Confirm Installation',
        'title' => 'Ready to Install',
        'intro' => 'Please review the configuration below. Clicking "Start Installation" will run the database migration and create the admin account.',

        /* Section 1 — Key settings */
        'section_key' => 'Key Settings',
        'app_name' => 'Application Name',
        'app_environment' => 'Environment',
        'app_debug' => 'App Debug',
        'app_url' => 'Application URL',
        'database' => 'Database',
        'database_host' => 'Database Host',
        'database_port' => 'Database Port',
        'database_username' => 'Database Username',
        'admin_name' => 'Admin Name',
        'admin_email' => 'Admin Email',

        /* Section 2 — Other settings */
        'section_other' => 'Other Settings',
        'log_level' => 'Log Level',
        'cache_driver' => 'Cache Driver',
        'session_driver' => 'Session Driver',
        'mail_mailer' => 'Mail Mailer',
        'mail_from_address' => 'Mail From Address',

        /* Warning and bottom actions */
        'admin_email_missing' => 'The admin account details were lost (session expired). Please go back and re-submit the configuration form.',
        'back' => 'Back to Configuration',
        'install' => 'Start Installation',
        'install_loading' => 'Installing, please wait...',
    ],

    /*
    |--------------------------------------------------------------------------
    | Install log
    |--------------------------------------------------------------------------
    | Messages written to the storage/installed file.
    */
    'installed' => [
        'success_log_message' => 'Aine Installer successfully INSTALLED on ',
    ],

    /*
    |--------------------------------------------------------------------------
    | Step 6 — Installation finished
    |--------------------------------------------------------------------------
    */
    'final' => [
        'title' => 'Installation Finished',
        'templateTitle' => 'Installation Finished',
        'finished' => 'Application has been successfully installed.',
        'migration' => 'Migration &amp; Seed Console Output:',
        'console' => 'Application Console Output:',
        'log' => 'Installation Log Entry:',
        'env' => 'Final .env File:',
        'exit' => 'Click here to exit',
    ],

    /*
    |--------------------------------------------------------------------------
    | Updater
    |--------------------------------------------------------------------------
    | Update wizard screens: Welcome → Overview → Finished.
    */
    'updater' => [
        'title' => 'Laravel Updater',

        'steps' => [
            'welcome' => 'Welcome',
            'overview' => 'Overview',
            'final' => 'Finalize',
        ],

        'welcome' => [
            'title' => 'Welcome To The Updater',
            'message' => 'Welcome to the update wizard.',
        ],

        'overview' => [
            'title' => 'Overview',
            'message' => 'There is 1 update.|There are :number updates.',
            'install_updates' => 'Install Updates',
        ],

        'final' => [
            'title' => 'Finished',
            'finished' => 'Application\'s database has been successfully updated.',
            'exit' => 'Click here to exit',
        ],

        'log' => [
            'success_message' => 'Aine Installer successfully UPDATED on ',
        ],
    ],
];
