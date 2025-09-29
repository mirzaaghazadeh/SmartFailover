<?php

namespace MirzaAghazadeh\SmartFailover;

use Illuminate\Support\ServiceProvider;
use MirzaAghazadeh\SmartFailover\Services\DatabaseFailoverManager;
use MirzaAghazadeh\SmartFailover\Services\CacheFailoverManager;
use MirzaAghazadeh\SmartFailover\Services\QueueFailoverManager;
use MirzaAghazadeh\SmartFailover\Services\HealthCheckManager;
use MirzaAghazadeh\SmartFailover\Services\NotificationManager;

class SmartFailoverServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/smart-failover.php', 'smart-failover'
        );

        $this->app->singleton(SmartFailover::class, function ($app) {
            return new SmartFailover(
                $app->make(DatabaseFailoverManager::class),
                $app->make(CacheFailoverManager::class),
                $app->make(QueueFailoverManager::class),
                $app->make(HealthCheckManager::class),
                $app->make(NotificationManager::class)
            );
        });

        $this->app->singleton(DatabaseFailoverManager::class, function ($app) {
            return new DatabaseFailoverManager($app['config'], $app['log']);
        });

        $this->app->singleton(CacheFailoverManager::class, function ($app) {
            return new CacheFailoverManager($app['config'], $app['log']);
        });

        $this->app->singleton(QueueFailoverManager::class, function ($app) {
            return new QueueFailoverManager($app['config'], $app['log']);
        });

        $this->app->singleton(HealthCheckManager::class, function ($app) {
            return new HealthCheckManager($app['config'], $app['log']);
        });

        $this->app->singleton(NotificationManager::class, function ($app) {
            return new NotificationManager($app['config'], $app['log']);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/smart-failover.php' => config_path('smart-failover.php'),
            ], 'smart-failover-config');

            $this->commands([
                Console\Commands\HealthCheckCommand::class,
                Console\Commands\TestFailoverCommand::class,
            ]);
        }

        // Register health check routes if enabled
        if (config('smart-failover.health_check.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/health.php');
        }
    }
}