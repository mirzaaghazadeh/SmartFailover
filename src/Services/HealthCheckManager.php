<?php

namespace MirzaAghazadeh\SmartFailover\Services;

use Illuminate\Contracts\Config\Repository as Config;
use Psr\Log\LoggerInterface;

class HealthCheckManager
{
    protected Config $config;
    protected LoggerInterface $logger;
    protected array $services = [];
    protected int $timeout;

    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->timeout = $config->get('smart-failover.health_check.timeout', 5);
    }

    /**
     * Check health of all configured services.
     */
    public function checkAll(array $serviceConfigs = []): array
    {
        $results = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'services' => [],
            'summary' => [
                'total' => 0,
                'healthy' => 0,
                'unhealthy' => 0,
            ],
        ];

        $enabledServices = $this->config->get('smart-failover.health_check.services', []);

        // Check database services
        if ($enabledServices['database'] ?? false) {
            $databaseManager = app(DatabaseFailoverManager::class);
            $dbResults = $databaseManager->checkHealth($serviceConfigs['database'] ?? []);
            $results['services']['database'] = $dbResults;
        }

        // Check cache services
        if ($enabledServices['cache'] ?? false) {
            $cacheManager = app(CacheFailoverManager::class);
            $cacheResults = $cacheManager->checkHealth($serviceConfigs['cache'] ?? []);
            $results['services']['cache'] = $cacheResults;
        }

        // Check queue services
        if ($enabledServices['queue'] ?? false) {
            $queueManager = app(QueueFailoverManager::class);
            $queueResults = $queueManager->checkHealth($serviceConfigs['queue'] ?? []);
            $results['services']['queue'] = $queueResults;
        }

        // Check storage services
        if ($enabledServices['storage'] ?? false) {
            $results['services']['storage'] = $this->checkStorageHealth();
        }

        // Check mail services
        if ($enabledServices['mail'] ?? false) {
            $results['services']['mail'] = $this->checkMailHealth();
        }

        // Calculate summary
        $this->calculateSummary($results);

        // Log health check results
        $this->logger->info('Health check completed', [
            'status' => $results['status'],
            'summary' => $results['summary'],
        ]);

        return $results;
    }

    /**
     * Check if a specific service is healthy.
     */
    public function isServiceHealthy(string $service): bool
    {
        try {
            switch ($service) {
                case 'database':
                    $manager = app(DatabaseFailoverManager::class);
                    $status = $manager->getHealthStatus();
                    break;
                case 'cache':
                    $manager = app(CacheFailoverManager::class);
                    $status = $manager->getHealthStatus();
                    break;
                case 'queue':
                    $manager = app(QueueFailoverManager::class);
                    $status = $manager->getHealthStatus();
                    break;
                default:
                    return false;
            }

            return !empty($status) && in_array(true, $status, true);
        } catch (\Exception $e) {
            $this->logger->error('Failed to check service health', [
                'service' => $service,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check storage health.
     */
    protected function checkStorageHealth(): array
    {
        $results = [];
        $disks = ['local', 'public'];

        // Add configured disks
        if ($this->config->has('filesystems.disks')) {
            $configuredDisks = array_keys($this->config->get('filesystems.disks', []));
            $disks = array_merge($disks, $configuredDisks);
            $disks = array_unique($disks);
        }

        foreach ($disks as $disk) {
            try {
                $startTime = microtime(true);

                // Test file operations
                $testFile = 'smart_failover_health_check_' . time() . '.txt';
                $testContent = 'health check test';

                \Storage::disk($disk)->put($testFile, $testContent);
                $retrieved = \Storage::disk($disk)->get($testFile);
                \Storage::disk($disk)->delete($testFile);

                $responseTime = (microtime(true) - $startTime) * 1000;

                if ($retrieved === $testContent) {
                    $results[$disk] = [
                        'disk' => $disk,
                        'status' => 'healthy',
                        'response_time_ms' => round($responseTime, 2),
                        'checked_at' => now()->toISOString(),
                    ];
                } else {
                    throw new \Exception('File content mismatch');
                }
            } catch (\Exception $e) {
                $results[$disk] = [
                    'disk' => $disk,
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                    'checked_at' => now()->toISOString(),
                ];
            }
        }

        return $results;
    }

    /**
     * Check mail health.
     */
    protected function checkMailHealth(): array
    {
        $results = [];
        $mailers = ['smtp', 'log'];

        // Add configured mailers
        if ($this->config->has('mail.mailers')) {
            $configuredMailers = array_keys($this->config->get('mail.mailers', []));
            $mailers = array_merge($mailers, $configuredMailers);
            $mailers = array_unique($mailers);
        }

        foreach ($mailers as $mailer) {
            try {
                $startTime = microtime(true);

                // Test mailer configuration
                $mailerConfig = $this->config->get("mail.mailers.{$mailer}");

                if (!$mailerConfig) {
                    throw new \Exception('Mailer configuration not found');
                }

                $responseTime = (microtime(true) - $startTime) * 1000;

                $results[$mailer] = [
                    'mailer' => $mailer,
                    'status' => 'healthy',
                    'response_time_ms' => round($responseTime, 2),
                    'checked_at' => now()->toISOString(),
                ];
            } catch (\Exception $e) {
                $results[$mailer] = [
                    'mailer' => $mailer,
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                    'checked_at' => now()->toISOString(),
                ];
            }
        }

        return $results;
    }

    /**
     * Calculate health check summary.
     */
    protected function calculateSummary(array &$results): void
    {
        $total = 0;
        $healthy = 0;
        $unhealthy = 0;

        foreach ($results['services'] as $serviceType => $services) {
            foreach ($services as $service) {
                $total++;
                if ($service['status'] === 'healthy') {
                    $healthy++;
                } else {
                    $unhealthy++;
                }
            }
        }

        $results['summary'] = [
            'total' => $total,
            'healthy' => $healthy,
            'unhealthy' => $unhealthy,
        ];

        // Set overall status
        $results['status'] = $unhealthy === 0 ? 'healthy' : 'degraded';
        if ($healthy === 0 && $total > 0) {
            $results['status'] = 'unhealthy';
        }
    }

    /**
     * Get health check route response.
     */
    public function getHealthResponse(): array
    {
        $healthData = $this->checkAll();

        return [
            'status' => $healthData['status'],
            'timestamp' => $healthData['timestamp'],
            'services' => $healthData['services'],
            'summary' => $healthData['summary'],
            'version' => $this->getPackageVersion(),
        ];
    }

    /**
     * Get package version.
     */
    protected function getPackageVersion(): string
    {
        try {
            $composerPath = __DIR__ . '/../../composer.json';
            if (file_exists($composerPath)) {
                $composer = json_decode(file_get_contents($composerPath), true);
                return $composer['version'] ?? '1.0.0';
            }
        } catch (\Exception $e) {
            // Ignore errors
        }

        return '1.0.0';
    }
}
