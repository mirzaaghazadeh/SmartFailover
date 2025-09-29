<?php

namespace Mirzaaghazadeh\SmartFailover;

use Illuminate\Support\ServiceProvider;
use Override;

class SmartFailoverServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/smart-failover.php',
            'smart-failover'
        );

        $this->app->singleton('smart-failover', function ($app) {
            return new SmartFailover(
                $app->make(Services\DatabaseFailoverManager::class),
                $app->make(Services\CacheFailoverManager::class),
                $app->make(Services\QueueFailoverManager::class),
                $app->make(Services\HealthCheckManager::class),
                $app->make(Services\NotificationManager::class)
            );
        });

        // Register individual managers
        $this->app->singleton(Services\DatabaseFailoverManager::class, function ($app) {
            return new Services\DatabaseFailoverManager($app['config'], $app['log']);
        });

        $this->app->singleton(Services\CacheFailoverManager::class, function ($app) {
            return new Services\CacheFailoverManager($app['config'], $app['log']);
        });

        $this->app->singleton(Services\QueueFailoverManager::class, function ($app) {
            return new Services\QueueFailoverManager($app['config'], $app['log']);
        });

        $this->app->singleton(Services\HealthCheckManager::class, function ($app) {
            return new Services\HealthCheckManager($app['config'], $app['log']);
        });

        $this->app->singleton(Services\NotificationManager::class, function ($app) {
            return new Services\NotificationManager($app['config'], $app['log']);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/smart-failover.php' => config_path('smart-failover.php'),
            ], 'smart-failover-config');

            $this->commands([
                Console\Commands\HealthCheckCommand::class,
                Console\Commands\TestFailoverCommand::class,
            ]);
        }
        // Register health check routes if enabled
        if (config('smart-failover.health_check.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/health.php');
        }
    }
}
