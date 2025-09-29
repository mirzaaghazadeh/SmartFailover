<?php

namespace Mirzaaghazadeh\SmartFailover;

use Closure;
use Mirzaaghazadeh\SmartFailover\Services\CacheFailoverManager;
use Mirzaaghazadeh\SmartFailover\Services\DatabaseFailoverManager;
use Mirzaaghazadeh\SmartFailover\Services\HealthCheckManager;
use Mirzaaghazadeh\SmartFailover\Services\NotificationManager;
use Mirzaaghazadeh\SmartFailover\Services\QueueFailoverManager;

class SmartFailover
{
    protected DatabaseFailoverManager $databaseManager;
    protected CacheFailoverManager $cacheManager;
    protected QueueFailoverManager $queueManager;
    protected HealthCheckManager $healthManager;
    protected NotificationManager $notificationManager;

    protected array $databaseConnections = [];
    protected array $cacheStores = [];
    protected array $queueConnections = [];

    public function __construct(
        DatabaseFailoverManager $databaseManager,
        CacheFailoverManager $cacheManager,
        QueueFailoverManager $queueManager,
        HealthCheckManager $healthManager,
        NotificationManager $notificationManager
    ) {
        $this->databaseManager = $databaseManager;
        $this->cacheManager = $cacheManager;
        $this->queueManager = $queueManager;
        $this->healthManager = $healthManager;
        $this->notificationManager = $notificationManager;
    }

    /**
     * Configure database failover connections.
     */
    public function db(string $primary, string $fallback = null): self
    {
        $this->databaseConnections = [
            'primary' => $primary,
            'fallback' => $fallback,
        ];

        return $this;
    }

    /**
     * Configure cache failover stores.
     */
    public function cache(string $primary, string $fallback = null): self
    {
        $this->cacheStores = [
            'primary' => $primary,
            'fallback' => $fallback,
        ];

        return $this;
    }

    /**
     * Configure queue failover connections.
     */
    public function queue(string $primary, string $fallback = null): self
    {
        $this->queueConnections = [
            'primary' => $primary,
            'fallback' => $fallback,
        ];

        return $this;
    }

    /**
     * Execute the given closure with failover protection.
     */
    public function send(Closure $callback): mixed
    {
        // Set up failover configurations
        if (!empty($this->databaseConnections)) {
            $this->databaseManager->setConnections($this->databaseConnections);
        }

        if (!empty($this->cacheStores)) {
            $this->cacheManager->setStores($this->cacheStores);
        }

        if (!empty($this->queueConnections)) {
            $this->queueManager->setConnections($this->queueConnections);
        }

        try {
            return $callback();
        } catch (\Exception $e) {
            // Log the exception and handle failover
            $this->notificationManager->notifyFailure($e);
            throw $e;
        }
    }

    /**
     * Execute database operations with failover.
     */
    public function database(Closure $callback): mixed
    {
        return $this->databaseManager->execute($callback, $this->databaseConnections);
    }

    /**
     * Execute cache operations with failover.
     */
    public function cacheOperation(Closure $callback): mixed
    {
        return $this->cacheManager->execute($callback, $this->cacheStores);
    }

    /**
     * Execute queue operations with failover.
     */
    public function queueOperation(Closure $callback): mixed
    {
        return $this->queueManager->execute($callback, $this->queueConnections);
    }

    /**
     * Get health status of all configured services.
     */
    public function health(): array
    {
        return $this->healthManager->checkAll([
            'database' => $this->databaseConnections,
            'cache' => $this->cacheStores,
            'queue' => $this->queueConnections,
        ]);
    }

    /**
     * Get health status of all configured services.
     */
    public function getHealthStatus(): array
    {
        return $this->healthManager->checkAll([
            'database' => $this->databaseConnections,
            'cache' => $this->cacheStores,
            'queue' => $this->queueConnections,
        ]);
    }

    /**
     * Check if a specific service is healthy.
     */
    public function isHealthy(string $service): bool
    {
        return $this->healthManager->isServiceHealthy($service);
    }

    /**
     * Reset all configurations.
     */
    public function reset(): self
    {
        $this->databaseConnections = [];
        $this->cacheStores = [];
        $this->queueConnections = [];

        return $this;
    }
}