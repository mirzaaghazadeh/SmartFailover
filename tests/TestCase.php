<?php

namespace Mirzaaghazadeh\SmartFailover\Tests;

use Mirzaaghazadeh\SmartFailover\SmartFailoverServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            SmartFailoverServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database configuration
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup cache configuration
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', [
            'driver' => 'array',
        ]);

        // Setup queue configuration
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('queue.connections.sync', [
            'driver' => 'sync',
        ]);

        // Setup SmartFailover configuration
        $app['config']->set('smart-failover', [
            'database' => [
                'primary' => 'testing',
                'fallbacks' => [],
                'health_check_interval' => 30,
                'retry_attempts' => 3,
                'retry_delay' => 1000,
                'graceful_degradation' => true,
            ],
            'cache' => [
                'primary' => 'array',
                'fallbacks' => [],
                'health_check_interval' => 15,
                'retry_attempts' => 2,
                'retry_delay' => 500,
            ],
            'queue' => [
                'primary' => 'sync',
                'fallbacks' => [],
                'health_check_interval' => 60,
                'retry_attempts' => 3,
                'retry_delay' => 1000,
            ],
            'health_checks' => [
                'enabled' => true,
                'cache_results' => false,
                'cache_ttl' => 60,
            ],
            'notifications' => [
                'enabled' => false,
                'throttle_minutes' => 5,
                'channels' => [],
            ],
            'logging' => [
                'enabled' => true,
                'level' => 'info',
                'channel' => 'single',
            ],
        ]);
    }

    protected function setUpDatabase(): void
    {
        // Create any necessary database tables for testing
        $this->artisan('migrate', ['--database' => 'testing']);
    }

    protected function getPackageAliases($app): array
    {
        return [
            'SmartFailover' => \Mirzaaghazadeh\SmartFailover\Facades\SmartFailover::class,
        ];
    }
}
