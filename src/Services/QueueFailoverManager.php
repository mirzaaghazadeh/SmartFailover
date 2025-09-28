<?php

namespace Mirzaaghazadeh\SmartFailover\Services;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Psr\Log\LoggerInterface;

class QueueFailoverManager
{
    protected Config $config;
    protected LoggerInterface $logger;
    protected array $connections = [];
    protected array $healthStatus = [];
    protected int $retryAttempts;
    protected int $retryDelay;
    protected bool $exponentialBackoff;

    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->retryAttempts = $config->get('smart-failover.queue.retry_attempts', 3);
        $this->retryDelay = $config->get('smart-failover.queue.retry_delay', 2000);
        $this->exponentialBackoff = $config->get('smart-failover.queue.exponential_backoff', true);
    }

    /**
     * Set queue connections for failover.
     */
    public function setConnections(array $connections): void
    {
        $this->connections = $connections;
    }

    /**
     * Execute queue operation with failover.
     */
    public function execute(Closure $callback, array $connections = null): mixed
    {
        $connections = $connections ?? $this->connections;

        if (empty($connections['primary'])) {
            throw new \InvalidArgumentException('Primary queue connection must be specified');
        }

        $primaryConnection = $connections['primary'];
        $fallbackConnection = $connections['fallback'] ?? null;

        // Try primary connection first
        try {
            return $this->executeWithConnection($callback, $primaryConnection);
        } catch (\Exception $e) {
            $this->logger->warning('Primary queue connection failed', [
                'connection' => $primaryConnection,
                'error' => $e->getMessage(),
            ]);

            // Try fallback connection if available
            if ($fallbackConnection) {
                try {
                    $this->logger->info('Switching to fallback queue connection', [
                        'fallback_connection' => $fallbackConnection,
                    ]);

                    return $this->executeWithConnection($callback, $fallbackConnection);
                } catch (\Exception $fallbackException) {
                    $this->logger->error('Fallback queue connection also failed', [
                        'fallback_connection' => $fallbackConnection,
                        'error' => $fallbackException->getMessage(),
                    ]);

                    throw $fallbackException;
                }
            }

            throw $e;
        }
    }

    /**
     * Execute callback with specific queue connection.
     */
    protected function executeWithConnection(Closure $callback, string $connection): mixed
    {
        $attempts = 0;
        $maxAttempts = $this->retryAttempts;
        $delay = $this->retryDelay;

        while ($attempts < $maxAttempts) {
            try {
                // Execute with the specified connection
                return $callback(Queue::connection($connection));
            } catch (\Exception $e) {
                $attempts++;

                if ($attempts >= $maxAttempts) {
                    throw $e;
                }

                // Calculate delay with exponential backoff if enabled
                $currentDelay = $this->exponentialBackoff
                    ? $delay * pow(2, $attempts - 1)
                    : $delay;

                // Wait before retry
                usleep($currentDelay * 1000);

                $this->logger->debug('Retrying queue operation', [
                    'connection' => $connection,
                    'attempt' => $attempts,
                    'max_attempts' => $maxAttempts,
                    'delay_ms' => $currentDelay,
                ]);
            }
        }

        throw new \RuntimeException('Maximum retry attempts exceeded');
    }

    /**
     * Push job to queue with failover.
     */
    public function push(string $job, array $data = [], string $queue = null): mixed
    {
        return $this->execute(function ($queueConnection) use ($job, $data, $queue) {
            return $queueConnection->push($job, $data, $queue);
        });
    }

    /**
     * Push job to queue later with failover.
     */
    public function later(\DateTimeInterface|\DateInterval|int $delay, string $job, array $data = [], string $queue = null): mixed
    {
        return $this->execute(function ($queueConnection) use ($delay, $job, $data, $queue) {
            return $queueConnection->later($delay, $job, $data, $queue);
        });
    }

    /**
     * Bulk push jobs to queue with failover.
     */
    public function bulk(array $jobs, array $data = [], string $queue = null): mixed
    {
        return $this->execute(function ($queueConnection) use ($jobs, $data, $queue) {
            return $queueConnection->bulk($jobs, $data, $queue);
        });
    }

    /**
     * Get queue size with failover.
     */
    public function size(string $queue = null): int
    {
        try {
            return $this->execute(function ($queueConnection) use ($queue) {
                return $queueConnection->size($queue);
            }) ?? 0;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get queue size', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Check health of queue connections.
     */
    public function checkHealth(array $connections = null): array
    {
        $connections = $connections ?? $this->connections;
        $results = [];

        foreach ($connections as $name => $connection) {
            if (!$connection) {
                continue;
            }

            try {
                $startTime = microtime(true);

                // Test queue connection by getting size
                $queueConnection = Queue::connection($connection);
                $size = $queueConnection->size();

                $responseTime = (microtime(true) - $startTime) * 1000;

                $results[$name] = [
                    'connection' => $connection,
                    'status' => 'healthy',
                    'queue_size' => $size,
                    'response_time_ms' => round($responseTime, 2),
                    'checked_at' => Carbon::now()->toISOString(),
                ];

                $this->healthStatus[$connection] = true;
            } catch (\Exception $e) {
                $results[$name] = [
                    'connection' => $connection,
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                    'checked_at' => Carbon::now()->toISOString(),
                ];

                $this->healthStatus[$connection] = false;
            }
        }

        return $results;
    }

    /**
     * Check if a specific connection is healthy.
     */
    public function isConnectionHealthy(string $connection): bool
    {
        return $this->healthStatus[$connection] ?? false;
    }

    /**
     * Get current health status.
     */
    public function getHealthStatus(): array
    {
        return $this->healthStatus;
    }

    /**
     * Get failed jobs count.
     */
    public function getFailedJobsCount(): int
    {
        try {
            return $this->execute(function ($queueConnection) {
                // This would depend on the queue driver implementation
                // For database queue, we could query failed_jobs table
                return 0; // Placeholder
            }) ?? 0;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get failed jobs count', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Retry failed jobs.
     */
    public function retryFailedJobs(array $jobIds = []): bool
    {
        try {
            return $this->execute(function ($queueConnection) use ($jobIds) {
                // Implementation would depend on queue driver
                // This is a placeholder for the retry logic
                return true;
            }) ?? false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to retry jobs', [
                'job_ids' => $jobIds,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
