# Laravel SmartFailover 🚀

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg?style=flat-square)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E9.0%7C%5E10.0%7C%5E11.0-red.svg?style=flat-square)](https://laravel.com)
[![Total Downloads](https://img.shields.io/packagist/dt/mirzaaghazadeh/smart-failover.svg?style=flat-square)](https://packagist.org/packages/mirzaaghazadeh/smart-failover)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg?style=flat-square)](https://opensource.org/licenses/MIT)

A comprehensive Laravel package that provides automatic failover for databases, caches, queues, and other critical services with intelligent routing and minimal code integration.

## 🎯 Problem It Solves

Most Laravel applications rely on single database, cache, or queue drivers. When these services fail, your application often crashes or becomes severely degraded. While there are tools for database replication or queue redundancy, none offer **automatic failover with intelligent routing** and **minimal code integration** in Laravel.

## ✨ Features

### 🔄 Automatic Service Failover
- **Database Failover**: Supports multiple database connections with automatic switching
- **Cache Failover**: Redis, Memcached, and other cache driver fallbacks
- **Queue Failover**: SQS, Redis, and database queue redundancy
- **Mail Failover**: Dynamic mail driver swapping (SMTP, SES, Mailgun, etc.)
- **Storage Failover**: S3, local, and other storage driver failover

### 🏥 Health Monitoring
- Real-time health checks for all services
- Performance metrics and response time tracking
- HTTP endpoints for external monitoring
- CLI commands for health status

### 🔧 Developer Experience
- **Minimal Code Changes**: Wrap your existing code with SmartFailover
- **Fluent API**: Intuitive and readable configuration
- **Graceful Degradation**: Continues operation even when services fail
- **Comprehensive Logging**: Track failures and performance

### 📊 Advanced Features
- **Automatic Retry**: Exponential backoff for failed operations
- **Notifications**: Slack/Telegram alerts for service failures
- **Dashboard Ready**: Health endpoints for Laravel Nova/Filament
- **Zero Downtime**: Seamless failover without service interruption

## 📦 Installation

Install the package via Composer:

```bash
composer require mirzaaghazadeh/smart-failover
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="MirzaAghazadeh\SmartFailover\SmartFailoverServiceProvider" --tag="config"
```

Publish the migration files (optional):

```bash
php artisan vendor:publish --provider="MirzaAghazadeh\SmartFailover\SmartFailoverServiceProvider" --tag="migrations"
```

## ⚙️ Configuration

The configuration file `config/smart-failover.php` allows you to configure all aspects of the failover system:

```php
return [
    'database' => [
        'primary' => 'mysql',
        'fallbacks' => ['mysql_backup', 'sqlite_fallback'],
        'health_check_interval' => 30, // seconds
        'retry_attempts' => 3,
        'retry_delay' => 1000, // milliseconds
    ],
    
    'cache' => [
        'primary' => 'redis',
        'fallbacks' => ['memcached', 'array'],
        'health_check_interval' => 15,
        'retry_attempts' => 2,
    ],
    
    'queue' => [
        'primary' => 'sqs',
        'fallbacks' => ['redis', 'database'],
        'health_check_interval' => 60,
        'retry_attempts' => 3,
    ],
    
    'notifications' => [
        'slack' => [
            'webhook_url' => env('SMART_FAILOVER_SLACK_WEBHOOK'),
            'channel' => '#alerts',
        ],
        'telegram' => [
            'bot_token' => env('SMART_FAILOVER_TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('SMART_FAILOVER_TELEGRAM_CHAT_ID'),
        ],
    ],
];
```

## 🚀 Usage

### Basic Usage

The most common usage pattern with the fluent API:

```php
use MirzaAghazadeh\SmartFailover\Facades\SmartFailover;

// Database, Cache, and Queue failover in one call
SmartFailover::db('mysql_primary', ['mysql_backup'])
    ->cache('redis', ['memcached'])
    ->queue('sqs', ['redis'])
    ->execute(function() {
        // Your normal application code here
        $user = User::create($userData);
        Cache::put('user_' . $user->id, $user, 3600);
        ProcessUserJob::dispatch($user);
        
        return $user;
    });
```

### Database Failover

```php
use MirzaAghazadeh\SmartFailover\Facades\SmartFailover;

// Simple database failover
SmartFailover::db('mysql_primary', ['mysql_backup'])
    ->execute(function() {
        return User::where('active', true)->get();
    });

// With graceful degradation
SmartFailover::db('mysql_primary', ['mysql_backup'])
    ->gracefulDegradation(function() {
        // Return cached data or default response when all DBs fail
        return Cache::get('users_fallback', collect());
    })
    ->execute(function() {
        return User::all();
    });
```

### Cache Failover

```php
// Cache with multiple fallbacks
SmartFailover::cache('redis', ['memcached', 'array'])
    ->execute(function() {
        Cache::put('key', 'value', 3600);
        return Cache::get('key');
    });

// Cache-specific operations
$cacheManager = app(CacheFailoverManager::class);
$cacheManager->setStores('redis', ['memcached']);

// Store with failover
$cacheManager->put('user_123', $userData, 3600);

// Retrieve with failover
$userData = $cacheManager->get('user_123');
```

### Queue Failover

```php
// Queue failover
SmartFailover::queue('sqs', ['redis', 'database'])
    ->execute(function() {
        ProcessOrderJob::dispatch($order);
        SendEmailJob::dispatch($user, $email);
    });

// Direct queue manager usage
$queueManager = app(QueueFailoverManager::class);
$queueManager->setConnections('sqs', ['redis']);
$queueManager->push(new ProcessOrderJob($order));
```

### Mail Failover

```php
use MirzaAghazadeh\SmartFailover\Services\MailFailoverManager;

$mailManager = app(MailFailoverManager::class);
$mailManager->setMailers('ses', ['smtp', 'mailgun']);

// Send with failover
$mailManager->send(new WelcomeEmail($user));

// Queue with failover
$mailManager->queue(new NewsletterEmail($subscribers));
```

### Storage Failover

```php
use MirzaAghazadeh\SmartFailover\Services\StorageFailoverManager;

$storageManager = app(StorageFailoverManager::class);
$storageManager->setDisks('s3', ['local', 'backup_s3']);

// Store file with failover
$storageManager->put('uploads/file.jpg', $fileContents);

// Retrieve file with failover
$contents = $storageManager->get('uploads/file.jpg');

// Sync file across all disks
$results = $storageManager->sync('important/backup.sql', $sqlDump);
```

## 🏥 Health Monitoring

### HTTP Health Endpoints

SmartFailover automatically registers health check routes:

```bash
# Overall health status
GET /health

# Detailed health information
GET /health/detailed

# Service-specific health checks
GET /health/database
GET /health/cache
GET /health/queue
GET /health/mail
GET /health/storage
```

Example health response:

```json
{
    "status": "healthy",
    "services": {
        "database": {
            "mysql_primary": {
                "status": "healthy",
                "response_time": 12.5,
                "last_checked": "2024-01-15T10:30:00Z"
            },
            "mysql_backup": {
                "status": "healthy",
                "response_time": 15.2,
                "last_checked": "2024-01-15T10:30:00Z"
            }
        },
        "cache": {
            "redis": {
                "status": "healthy",
                "response_time": 2.1,
                "last_checked": "2024-01-15T10:30:00Z"
            }
        }
    },
    "summary": {
        "total_services": 3,
        "healthy_services": 3,
        "degraded_services": 0,
        "unhealthy_services": 0,
        "average_response_time": 9.9
    }
}
```

### CLI Health Commands

```bash
# Check overall health
php artisan smart-failover:health

# Check specific service
php artisan smart-failover:health --service=database

# Detailed health information
php artisan smart-failover:health --detailed

# JSON output for monitoring tools
php artisan smart-failover:health --json
```

### Testing Failover

```bash
# Test all services
php artisan smart-failover:test all

# Test specific service
php artisan smart-failover:test database --primary=mysql --fallback=mysql_backup

# Simulate failure scenarios
php artisan smart-failover:test cache --simulate-failure
```

## 🔔 Notifications

Configure notifications for service failures:

### Slack Notifications

```php
// In config/smart-failover.php
'notifications' => [
    'slack' => [
        'webhook_url' => env('SMART_FAILOVER_SLACK_WEBHOOK'),
        'channel' => '#alerts',
        'username' => 'SmartFailover',
        'throttle_minutes' => 5, // Prevent spam
    ],
],
```

### Telegram Notifications

```php
'notifications' => [
    'telegram' => [
        'bot_token' => env('SMART_FAILOVER_TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('SMART_FAILOVER_TELEGRAM_CHAT_ID'),
        'throttle_minutes' => 5,
    ],
],
```

### Email Notifications

```php
'notifications' => [
    'email' => [
        'to' => ['admin@example.com', 'devops@example.com'],
        'subject' => 'SmartFailover Alert',
        'throttle_minutes' => 10,
    ],
],
```

## 🔧 Advanced Configuration

### Custom Health Checks

```php
use MirzaAghazadeh\SmartFailover\Services\HealthCheckManager;

$healthManager = app(HealthCheckManager::class);

// Add custom health check
$healthManager->addCustomCheck('payment_gateway', function() {
    // Your custom health check logic
    $response = Http::timeout(5)->get('https://api.stripe.com/v1/charges');
    
    return [
        'status' => $response->successful() ? 'healthy' : 'unhealthy',
        'response_time' => $response->transferStats->getTransferTime() * 1000,
        'details' => $response->successful() ? 'OK' : 'Failed to connect',
    ];
});
```

### Custom Retry Logic

```php
SmartFailover::db('mysql_primary', ['mysql_backup'])
    ->retryAttempts(5)
    ->retryDelay(2000) // 2 seconds
    ->retryMultiplier(1.5) // Exponential backoff
    ->execute(function() {
        // Your database operations
    });
```

### Graceful Degradation

```php
SmartFailover::cache('redis', ['memcached'])
    ->gracefulDegradation(function() {
        // Fallback when all cache services fail
        return [
            'user_preferences' => config('app.default_preferences'),
            'settings' => config('app.default_settings'),
        ];
    })
    ->execute(function() {
        return [
            'user_preferences' => Cache::get('user_prefs'),
            'settings' => Cache::get('app_settings'),
        ];
    });
```

## 📊 Dashboard Integration

### Laravel Nova

```php
// In your Nova dashboard
use MirzaAghazadeh\SmartFailover\Services\HealthCheckManager;

public function cards(Request $request)
{
    $healthManager = app(HealthCheckManager::class);
    $health = $healthManager->getHealthResponse();
    
    return [
        new SmartFailoverHealthCard($health),
    ];
}
```

### Filament

```php
// In your Filament widget
use MirzaAghazadeh\SmartFailover\Services\HealthCheckManager;

protected function getStats(): array
{
    $healthManager = app(HealthCheckManager::class);
    $health = $healthManager->checkAll();
    
    return [
        Stat::make('Services Status', $health['summary']['healthy_services'] . '/' . $health['summary']['total_services'])
            ->description('Healthy Services')
            ->color($health['status'] === 'healthy' ? 'success' : 'danger'),
    ];
}
```

## 🧪 Testing

Run the package tests:

```bash
composer test
```

Test your failover configuration:

```bash
# Test all configured services
php artisan smart-failover:test all

# Test with simulated failures
php artisan smart-failover:test database --simulate-failure
```

## 📈 Performance Considerations

### Connection Pooling

SmartFailover reuses connections when possible to minimize overhead:

```php
// Connections are pooled and reused
SmartFailover::db('mysql_primary', ['mysql_backup'])
    ->poolConnections(true)
    ->execute(function() {
        // Multiple queries use the same connection
        User::all();
        Order::all();
    });
```

### Async Health Checks

Enable background health checks to reduce request latency:

```php
// In config/smart-failover.php
'health_checks' => [
    'async' => true,
    'interval' => 30, // seconds
    'cache_results' => true,
    'cache_ttl' => 60, // seconds
],
```

## 🔒 Security Considerations

- Never log sensitive connection details
- Use environment variables for credentials
- Implement proper access controls for health endpoints
- Consider rate limiting for health check endpoints

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## 🔐 Security

If you discover any security-related issues, please email hi@navid.tr instead of using the issue tracker.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 👨‍💻 Author

**Navid Mirzaaghazadeh**
- Email: hi@navid.tr
- GitHub: [@mirzaaghazadeh](https://github.com/mirzaaghazadeh)

## 🙏 Acknowledgments

- Laravel Framework for providing an excellent foundation
- The Laravel community for inspiration and feedback
- All contributors who help improve this package

---

Made with ❤️ for the Laravel community