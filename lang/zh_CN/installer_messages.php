<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 全局共享文案
    |--------------------------------------------------------------------------
    | 安装向导所有页面通用的标题与导航按钮文案。
    */

    'title' => 'Laravel安装程序',
    'next' => '下一步',
    'back' => '上一步',
    'finish' => '安装',
    'install' => '安装',

    'forms' => [
        'errorTitle' => '发生以下错误:',
    ],

    /*
    |--------------------------------------------------------------------------
    | 顶部步骤条
    |--------------------------------------------------------------------------
    | 安装向导顶部进度条文案，按显示顺序排列：
    | 欢迎 → 环境要求 → 权限 → 配置 → 确认安装 → 完成。
    */
    'steps' => [
        'welcome' => '欢迎',
        'requirements' => '环境要求',
        'permissions' => '权限',
        'environment' => '配置',
        'confirm' => '确认安装',
        'database' => '数据库',
        'final' => '完成',
    ],

    /*
    |--------------------------------------------------------------------------
    | 第 1 步：欢迎页
    |--------------------------------------------------------------------------
    */
    'welcome' => [
        'templateTitle' => '欢迎',
        'title' => '欢迎来到Laravel安装程序',
        'message' => '欢迎来到安装向导.',
        'next' => '检查环境要求',
    ],

    /*
    |--------------------------------------------------------------------------
    | 第 2 步：环境要求页
    |--------------------------------------------------------------------------
    */
    'requirements' => [
        'templateTitle' => '第二步 | 环境要求',
        'title' => '环境要求',
        'next' => '检查权限',
    ],

    /*
    |--------------------------------------------------------------------------
    | 第 3 步：目录权限页
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'templateTitle' => '第三步 | 权限',
        'title' => '权限',
        'next' => '配置环境',
    ],

    /*
    |--------------------------------------------------------------------------
    | 第 4 步：环境配置页
    |--------------------------------------------------------------------------
    | 4.1 配置方式菜单：表单向导 / 经典文本编辑器。
    | 4.2 表单向导：分 Tab 表单（环境 / 其他）。
    | 4.3 经典编辑器：直接编辑 .env 文本。
    */
    'environment' => [

        /*
         * 4.1 菜单——选择配置方式。
         */
        'menu' => [
            'templateTitle' => '第四步 | 配置',
            'title' => '配置',
            'desc' => '请选择您的应用程序的<code> .env </code>文件的配置方式.',
            'wizard-button' => '表单向导设置',
            'classic-button' => '经典文本编辑器',
        ],

        /*
         * 4.2 表单向导——分 Tab 表单。
         */
        'wizard' => [
            'templateTitle' => '第四步 | 配置 | 向导引导',
            'title' => '向导引导 <code>.env</code>文件',

            'tabs' => [
                'environment' => '环境',
                'database' => '数据库',
                'application' => '应用程序',
            ],

            'form' => [

                /*
                 * 环境 Tab · 应用设置。
                 */
                'name_required' => '环境名称是必需的.',
                'app_name_label' => '应用程序名称',
                'app_name_placeholder' => '应用程序名称',
                'app_environment_label' => '应用程序环境',
                'app_environment_label_local' => '本地',
                'app_environment_label_developement' => '开发',
                'app_environment_label_qa' => 'Qa',
                'app_environment_label_production' => '生产',
                'app_environment_label_other' => '其他',
                'app_environment_placeholder_other' => '输入您的环境...',
                'app_debug_label' => '应用程序调试',
                'app_debug_label_true' => '真',
                'app_debug_label_false' => '假',
                'log_level_label' => '应用程序日志级别',
                'log_level_label_debug' => '调试',
                'log_level_label_info' => '信息',
                'log_level_label_notice' => '注意',
                'log_level_label_warning' => '警告',
                'log_level_label_error' => '错误',
                'log_level_label_critical' => '严重',
                'log_level_label_alert' => '警报',
                'log_level_label_emergency' => '紧急',
                'app_url_label' => '应用程序URL',
                'app_url_placeholder' => '应用程序URL',

                /*
                 * 环境 Tab · 数据库连接。
                 */
                'db_connection_failed' => '无法连接到数据库.',
                'db_connection_label' => '数据库连接',
                'db_connection_label_mysql' => 'mysql',
                'db_connection_label_sqlite' => 'sqlite',
                'db_connection_label_pgsql' => 'pgsql',
                'db_connection_label_sqlsrv' => 'sqlsrv',
                'db_host_label' => '数据库主机',
                'db_host_placeholder' => '数据库主机',
                'db_port_label' => '数据库端口',
                'db_port_placeholder' => '数据库端口',
                'db_name_label' => '数据库名称',
                'db_name_placeholder' => '数据库名称',
                'sqlite_path_placeholder' => '（可选）如 database/installer.sqlite，留空则使用默认 database/database.sqlite',
                'db_username_label' => '数据库用户名',
                'db_username_placeholder' => '数据库用户名',
                'db_password_label' => '数据库密码',
                'db_password_placeholder' => '数据库密码',

                /*
                 * “其他”Tab 内容与管理员账号区块。
                 */
                'app_tabs' => [

                    /*
                     * 环境 Tab · 管理员账号（页面底部）。
                     */
                    'admin_label' => '管理员账号',
                    'admin_name_label' => '管理员姓名',
                    'admin_name_placeholder' => '管理员姓名',
                    'admin_email_label' => '管理员邮箱',
                    'admin_email_placeholder' => 'admin@example.com',
                    'admin_password_label' => '管理员密码',
                    'admin_password_placeholder' => '至少 8 个字符',

                    /*
                     * 其他 Tab · 广播 / 缓存 / 会话 / 队列。
                     */
                    'other_label' => '其他',
                    'more_info' => '更多信息',
                    'broadcasting_title' => '广播，缓存，会话和队列',
                    'broadcasting_label' => '广播驱动程序',
                    'broadcasting_placeholder' => '广播驱动程序',
                    'cache_label' => '缓存驱动程序',
                    'cache_placeholder' => '缓存驱动程序',
                    'session_label' => '会话驱动程序',
                    'session_placeholder' => '会话驱动程序',
                    'queue_label' => '队列驱动程序',
                    'queue_placeholder' => '队列驱动程序',

                    /*
                     * 其他 Tab · Redis。
                     */
                    'redis_label' => 'Redis 驱动程序',
                    'redis_host' => 'Redis 主机',
                    'redis_host_placeholder' => '127.0.0.1',
                    'redis_password' => 'Redis 密码',
                    'redis_password_placeholder' => '无密码则留空',
                    'redis_port' => 'Redis 端口',
                    'redis_port_placeholder' => '6379',

                    /*
                     * 其他 Tab · 邮件（全部为可选字段）。
                     */
                    'mail_label' => '邮件',
                    'mail_driver_label' => '邮件驱动程序',
                    'mail_option_log' => '日志（不发送）',
                    'mail_option_smtp' => 'SMTP',
                    'mail_option_sendmail' => 'Sendmail',
                    'mail_driver_placeholder' => '邮件驱动程序',
                    'mail_host_label' => '邮件主机',
                    'mail_host_placeholder' => '邮件主机',
                    'mail_port_label' => '邮件端口',
                    'mail_port_placeholder' => '邮件端口',
                    'mail_username_label' => '邮件用户名',
                    'mail_username_placeholder' => '邮件用户名',
                    'mail_password_label' => '邮件密码',
                    'mail_password_placeholder' => '邮件密码',
                    'mail_encryption_label' => '邮件加密',
                    'mail_encryption_placeholder' => '邮件加密',
                    'mail_optional_hint' => '邮件配置为可选项。保持 "Log" 即可跳过 SMTP 设置，之后可在 .env 中配置',
                    'mail_from_address_label' => '发件地址',
                    'mail_from_address_placeholder' => 'noreply@example.com',
                    'mail_from_name_label' => '发件人名称',
                    'mail_from_name_placeholder' => 'Aine',

                    /*
                     * 其他 Tab · Pusher。
                     */
                    'pusher_label' => '推送',
                    'pusher_app_id_label' => 'Pusher 应用程序 Id',
                    'pusher_app_id_placeholder' => 'Pusher 应用程序 Id',
                    'pusher_app_key_label' => 'Pusher 应用程序 Key',
                    'pusher_app_key_placeholder' => 'Pusher 应用程序 Key',
                    'pusher_app_secret_label' => 'Pusher 应用程序 Secret',
                    'pusher_app_secret_placeholder' => 'Pusher 应用程序 Secret',
                ],

                'buttons' => [
                    'setup_application' => '设置应用程序',
                    'install' => '安装',
                ],
            ],
        ],

        /*
         * 4.3 经典编辑器——直接编辑 .env 文本。
         */
        'classic' => [
            'templateTitle' => '第四步 | 配置 | 经典编辑器',
            'title' => '经典配置编辑器',
            'save' => '保存 .env',
            'back' => '使用向导表单',
            'install' => '安装',
        ],

        'success' => '您的 .env 文件设置已保存。',
        'errors' => '无法保存 .env 文件，请手动创建它。',
    ],

    /*
    |--------------------------------------------------------------------------
    | 第 5 步：确认安装页
    |--------------------------------------------------------------------------
    | 键按确认页的布局顺序排列：
    | 关键设置 → 其他设置 → 缺少管理员提示 → 底部操作。
    */
    'confirm' => [
        'templateTitle' => '确认安装',
        'title' => '准备安装',
        'intro' => '请确认以下配置。点击"开始安装"后将执行数据库迁移并创建管理员账号。',

        /* 分区一：关键设置 */
        'section_key' => '关键设置',
        'app_name' => '应用名称',
        'app_environment' => '运行环境',
        'app_debug' => '调试模式',
        'app_url' => '应用地址',
        'database' => '数据库',
        'database_host' => '数据库主机',
        'database_port' => '数据库端口',
        'database_username' => '数据库用户名',
        'admin_name' => '管理员姓名',
        'admin_email' => '管理员邮箱',

        /* 分区二：其他设置 */
        'section_other' => '其他设置',
        'log_level' => '日志级别',
        'cache_driver' => '缓存驱动',
        'session_driver' => 'Session 驱动',
        'mail_mailer' => '邮件驱动',
        'mail_from_address' => '发件人地址',

        /* 提示与底部操作 */
        'admin_email_missing' => '管理员账号信息已丢失（会话过期），请返回并重新提交配置表单。',
        'back' => '返回修改配置',
        'install' => '开始安装',
        'install_loading' => '安装中，请稍候...',
    ],

    /*
    |--------------------------------------------------------------------------
    | 安装日志
    |--------------------------------------------------------------------------
    | 写入 storage/installed 文件的提示文案。
    */
    'installed' => [
        'success_log_message' => 'Laravel 安装程序成功安装于 ',
    ],

    /*
    |--------------------------------------------------------------------------
    | 第 6 步：安装完成页
    |--------------------------------------------------------------------------
    */
    'final' => [
        'title' => '安装完成',
        'templateTitle' => '安装完成',
        'finished' => '应用程序已成功安装。',
        'migration' => '迁移控制台输出:',
        'console' => '应用程序控制台输出:',
        'log' => '安装日志记录:',
        'env' => '最终 .env 文件:',
        'exit' => '点击这里退出',
    ],

    /*
    |--------------------------------------------------------------------------
    | 更新向导
    |--------------------------------------------------------------------------
    | 更新向导页面：欢迎 → 概览 → 完成。
    */
    'updater' => [
        'title' => 'Laravel 更新程序',

        'steps' => [
            'welcome' => '欢迎',
            'overview' => '概述',
            'final' => '完成',
        ],

        'welcome' => [
            'title' => '欢迎来到更新向导',
            'message' => '欢迎来到更新向导。',
        ],

        'overview' => [
            'title' => '概述',
            'message' => '有 1 个更新.|有 :number 个更新.',
            'install_updates' => '安装更新',
        ],

        'final' => [
            'title' => '完成',
            'finished' => '应用程序的数据库已成功更新。',
            'exit' => '点击这里退出',
        ],

        'log' => [
            'success_message' => 'Laravel 安装程序成功更新于 ',
        ],
    ],
];
