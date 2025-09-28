<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SmartFailover Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration options for the SmartFailover
    | package. You can configure database, cache, queue failover settings,
    | health checks, and notification preferences.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Database Failover Configuration
    |--------------------------------------------------------------------------
    */
    'database' => [
        'enabled' => env('SMART_FAILOVER_DB_ENABLED', true),
        'connections' => [
            'primary' => env('DB_CONNECTION', 'mysql'),
            'fallback' => env('DB_FALLBACK_CONNECTION', 'mysql_backup'),
        ],
        'health_check_interval' => env('SMART_FAILOVER_DB_HEALTH_INTERVAL', 30), // seconds
        'retry_attempts' => env('SMART_FAILOVER_DB_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('SMART_FAILOVER_DB_RETRY_DELAY', 1000), // milliseconds
        'graceful_degradation' => env('SMART_FAILOVER_DB_GRACEFUL_DEGRADATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Failover Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('SMART_FAILOVER_CACHE_ENABLED', true),
        'stores' => [
            'primary' => env('CACHE_DRIVER', 'redis'),
            'fallback' => env('CACHE_FALLBACK_DRIVER', 'file'),
        ],
        'health_check_interval' => env('SMART_FAILOVER_CACHE_HEALTH_INTERVAL', 30),
        'retry_attempts' => env('SMART_FAILOVER_CACHE_RETRY_ATTEMPTS', 2),
        'retry_delay' => env('SMART_FAILOVER_CACHE_RETRY_DELAY', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Failover Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'enabled' => env('SMART_FAILOVER_QUEUE_ENABLED', true),
        'connections' => [
            'primary' => env('QUEUE_CONNECTION', 'redis'),
            'fallback' => env('QUEUE_FALLBACK_CONNECTION', 'database'),
        ],
        'health_check_interval' => env('SMART_FAILOVER_QUEUE_HEALTH_INTERVAL', 60),
        'retry_attempts' => env('SMART_FAILOVER_QUEUE_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('SMART_FAILOVER_QUEUE_RETRY_DELAY', 2000),
        'exponential_backoff' => env('SMART_FAILOVER_QUEUE_EXPONENTIAL_BACKOFF', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    */
    'health_check' => [
        'enabled' => env('SMART_FAILOVER_HEALTH_CHECK_ENABLED', true),
        'route_enabled' => env('SMART_FAILOVER_HEALTH_ROUTE_ENABLED', true),
        'route_path' => env('SMART_FAILOVER_HEALTH_ROUTE_PATH', '/health/smart-failover'),
        'middleware' => ['web'],
        'timeout' => env('SMART_FAILOVER_HEALTH_TIMEOUT', 5), // seconds
        'services' => [
            'database' => true,
            'cache' => true,
            'queue' => true,
            'storage' => false,
            'mail' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'enabled' => env('SMART_FAILOVER_NOTIFICATIONS_ENABLED', false),
        'channels' => [
            'slack' => [
                'enabled' => env('SMART_FAILOVER_SLACK_ENABLED', false),
                'webhook_url' => env('SMART_FAILOVER_SLACK_WEBHOOK_URL'),
                'channel' => env('SMART_FAILOVER_SLACK_CHANNEL', '#alerts'),
                'username' => env('SMART_FAILOVER_SLACK_USERNAME', 'SmartFailover'),
            ],
            'telegram' => [
                'enabled' => env('SMART_FAILOVER_TELEGRAM_ENABLED', false),
                'bot_token' => env('SMART_FAILOVER_TELEGRAM_BOT_TOKEN'),
                'chat_id' => env('SMART_FAILOVER_TELEGRAM_CHAT_ID'),
            ],
            'email' => [
                'enabled' => env('SMART_FAILOVER_EMAIL_ENABLED', false),
                'to' => env('SMART_FAILOVER_EMAIL_TO'),
                'from' => env('SMART_FAILOVER_EMAIL_FROM', 'noreply@example.com'),
            ],
        ],
        'throttle' => [
            'enabled' => true,
            'minutes' => 15, // Don't send same alert more than once per 15 minutes
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('SMART_FAILOVER_LOGGING_ENABLED', true),
        'channel' => env('SMART_FAILOVER_LOG_CHANNEL', 'single'),
        'level' => env('SMART_FAILOVER_LOG_LEVEL', 'info'),
        'include_metrics' => env('SMART_FAILOVER_LOG_METRICS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dynamic Driver Swapping
    |--------------------------------------------------------------------------
    */
    'dynamic_drivers' => [
        'mail' => [
            'enabled' => env('SMART_FAILOVER_MAIL_ENABLED', false),
            'primary' => env('MAIL_MAILER', 'smtp'),
            'fallback' => env('MAIL_FALLBACK_MAILER', 'log'),
        ],
        'storage' => [
            'enabled' => env('SMART_FAILOVER_STORAGE_ENABLED', false),
            'primary' => env('FILESYSTEM_DISK', 's3'),
            'fallback' => env('FILESYSTEM_FALLBACK_DISK', 'local'),
        ],
    ],

];