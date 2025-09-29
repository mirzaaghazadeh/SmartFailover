<?php

namespace Mirzaaghazadeh\SmartFailover\Services;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

class DatabaseFailoverManager
{
    protected Config $config;
    protected LoggerInterface $logger;
    protected array $connections = [];
    protected array $healthStatus = [];
    protected int $retryAttempts;
    protected int $retryDelay;

    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->retryAttempts = $config->get('smart-failover.database.retry_attempts', 3);
        $this->retryDelay = $config->get('smart-failover.database.retry_delay', 1000);
    }

    /**
     * Set database connections for failover.
     */
    public function setConnections(array $connections): void
    {
        $this->connections = $connections;
    }

    /**
     * Execute database operation with failover.
     */
    public function execute(Closure $callback, array $connections = null): mixed
    {
        $connections = $connections ?? $this->connections;

        if (empty($connections['primary'])) {
            throw new \InvalidArgumentException('Primary database connection must be specified');
        }

        $primaryConnection = $connections['primary'];
        $fallbackConnection = $connections['fallback'] ?? null;

        // Try primary connection first
        try {
            return $this->executeWithConnection($callback, $primaryConnection);
        } catch (QueryException|\Exception $e) {
            $this->logger->warning('Primary database connection failed', [
                'connection' => $primaryConnection,
                'error' => $e->getMessage(),
            ]);

            // Try fallback connection if available
            if ($fallbackConnection) {
                try {
                    $this->logger->info('Switching to fallback database connection', [
                        'fallback_connection' => $fallbackConnection,
                    ]);

                    return $this->executeWithConnection($callback, $fallbackConnection);
                } catch (QueryException $fallbackException) {
                    $this->logger->error('Fallback database connection also failed', [
                        'fallback_connection' => $fallbackConnection,
                        'error' => $fallbackException->getMessage(),
                    ]);

                    // Check if graceful degradation is enabled
                    if ($this->config->get('smart-failover.database.graceful_degradation', true)) {
                        return $this->handleGracefulDegradation($e, $fallbackException);
                    }

                    throw $fallbackException;
                }
            }

            throw $e;
        }
    }

    /**
     * Execute callback with specific database connection.
     */
    protected function executeWithConnection(Closure $callback, string $connection): mixed
    {
        $attempts = 0;
        $maxAttempts = $this->retryAttempts;

        while ($attempts < $maxAttempts) {
            try {
                // Set the connection and execute
                DB::setDefaultConnection($connection);
                return $callback();
            } catch (QueryException $e) {
                $attempts++;

                if ($attempts >= $maxAttempts) {
                    throw $e;
                }

                // Wait before retry
                usleep($this->retryDelay * 1000);

                $this->logger->debug('Retrying database operation', [
                    'connection' => $connection,
                    'attempt' => $attempts,
                    'max_attempts' => $maxAttempts,
                ]);
            }
        }

        throw new \RuntimeException('Maximum retry attempts exceeded');
    }

    /**
     * Handle graceful degradation when all connections fail.
     */
    protected function handleGracefulDegradation(\Exception $primaryException, \Exception $fallbackException): mixed
    {
        $this->logger->critical('All database connections failed, entering graceful degradation mode', [
            'primary_error' => $primaryException->getMessage(),
            'fallback_error' => $fallbackException->getMessage(),
        ]);

        // Return null or empty result for graceful degradation
        // This allows the application to continue running with limited functionality
        return null;
    }

    /**
     * Check health of database connections.
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
                DB::connection($connection)->select('SELECT 1');
                $responseTime = (float) ((microtime(true) - $startTime) * 1000);

                $results[$name] = [
                    'connection' => $connection,
                    'status' => 'healthy',
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
}
