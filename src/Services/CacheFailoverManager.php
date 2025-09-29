final <?php

namespace Mirzaaghazadeh\SmartFailover\Services;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Psr\Log\LoggerInterface;

class CacheFailoverManager
{
    protected Config $config;
    protected LoggerInterface $logger;
    protected array $stores = [];
    protected array $healthStatus = [];
    protected int $retryAttempts;
    protected int $retryDelay;

    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->retryAttempts = $config->get('smart-failover.cache.retry_attempts', 2);
        $this->retryDelay = $config->get('smart-failover.cache.retry_delay', 500);
    }

    /**
     * Set cache stores for failover.
     */
    public function setStores(array $stores): void
    {
        $this->stores = $stores;
    }

    /**
     * Execute cache operation with failover.
     */
    public function execute(Closure $callback, array $stores = null): mixed
    {
        $stores = $stores ?? $this->stores;

        if (empty($stores['primary'])) {
            throw new \InvalidArgumentException('Primary cache store must be specified');
        }

        $primaryStore = $stores['primary'];
        $fallbackStore = $stores['fallback'] ?? null;

        // Try primary store first
        try {
            return $this->executeWithStore($callback, $primaryStore);
        } catch (\Exception $e) {
            $this->logger->warning('Primary cache store failed', [
                'store' => $primaryStore,
                'error' => $e->getMessage(),
            ]);

            // Try fallback store if available
            if ($fallbackStore) {
                try {
                    $this->logger->info('Switching to fallback cache store', [
                        'fallback_store' => $fallbackStore,
                    ]);

                    return $this->executeWithStore($callback, $fallbackStore);
                } catch (\Exception $fallbackException) {
                    $this->logger->error('Fallback cache store also failed', [
                        'fallback_store' => $fallbackStore,
                        'error' => $fallbackException->getMessage(),
                    ]);

                    // For cache operations, we can often continue without cache
                    return $this->handleCacheMiss($e, $fallbackException);
                }
            }

            // If no fallback, handle cache miss gracefully
            return $this->handleCacheMiss($e);
        }
    }

    /**
     * Execute callback with specific cache store.
     */
    protected function executeWithStore(Closure $callback, string $store): mixed
    {
        $attempts = 0;
        $maxAttempts = $this->retryAttempts;

        while ($attempts < $maxAttempts) {
            try {
                // Execute with the specified store
                return $callback(Cache::store($store));
            } catch (\Exception $e) {
                $attempts++;

                if ($attempts >= $maxAttempts) {
                    throw $e;
                }

                // Wait before retry
                usleep($this->retryDelay * 1000);

                $this->logger->debug('Retrying cache operation', [
                    'store' => $store,
                    'attempt' => $attempts,
                    'max_attempts' => $maxAttempts,
                ]);
            }
        }

        throw new \RuntimeException('Maximum retry attempts exceeded');
    }

    /**
     * Handle cache miss gracefully.
     */
    protected function handleCacheMiss(\Exception $primaryException, \Exception $fallbackException = null): mixed
    {
        $this->logger->warning('Cache operations failed, continuing without cache', [
            'primary_error' => $primaryException->getMessage(),
            'fallback_error' => $fallbackException?->getMessage(),
        ]);

        // Return null for cache misses - application should handle this gracefully
        return null;
    }

    /**
     * Get cache value with failover.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->execute(function ($cache) use ($key, $default) {
            return $cache->get($key, $default);
        });
    }

    /**
     * Put cache value with failover.
     */
    public function put(string $key, mixed $value, int $ttl = null): bool
    {
        try {
            return $this->execute(function ($cache) use ($key, $value, $ttl) {
                return $cache->put($key, $value, $ttl);
            }) ?? false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to cache value', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Remember cache value with failover.
     */
    public function remember(string $key, int $ttl, Closure $callback): mixed
    {
        try {
            return $this->execute(function ($cache) use ($key, $ttl, $callback) {
                return $cache->remember($key, $ttl, $callback);
            });
        } catch (\Exception $e) {
            $this->logger->warning('Cache remember failed, executing callback directly', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            // If cache fails, execute callback directly
            return $callback();
        }
    }

    /**
     * Forget cache value with failover.
     */
    public function forget(string $key): bool
    {
        try {
            return $this->execute(function ($cache) use ($key) {
                return $cache->forget($key);
            }) ?? false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to forget cache key', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check health of cache stores.
     */
    public function checkHealth(array $stores = null): array
    {
        $stores = $stores ?? $this->stores;
        $results = [];

        foreach ($stores as $name => $store) {
            if (!$store) {
                continue;
            }

            try {
                $startTime = microtime(true);
                $testKey = 'smart_failover_health_check_' . time();
                $testValue = 'test';

                Cache::store($store)->put($testKey, $testValue, 60);
                $retrieved = Cache::store($store)->get($testKey);
                Cache::store($store)->forget($testKey);

                $responseTime = ((float) microtime(true) - (float) $startTime) * 1000.0;

                if ($retrieved === $testValue) {
                    $results[$name] = [
                        'store' => $store,
                        'status' => 'healthy',
                        'response_time_ms' => round($responseTime, 2),
                        'checked_at' => Carbon::now()->toISOString(),
                    ];

                    $this->healthStatus[$store] = true;
                } else {
                    throw new \Exception('Cache value mismatch');
                }
            } catch (\Exception $e) {
                $results[$name] = [
                    'store' => $store,
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                    'checked_at' => Carbon::now()->toISOString(),
                ];

                $this->healthStatus[$store] = false;
            }
        }

        return $results;
    }

    /**
     * Check if a specific store is healthy.
     */
    public function isStoreHealthy(string $store): bool
    {
        return $this->healthStatus[$store] ?? false;
    }

    /**
     * Get current health status.
     */
    public function getHealthStatus(): array
    {
        return $this->healthStatus;
    }
}
