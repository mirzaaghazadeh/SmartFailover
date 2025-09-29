<?php

namespace MirzaAghazadeh\SmartFailover\Console\Commands;

use Illuminate\Console\Command;
use MirzaAghazadeh\SmartFailover\Services\HealthCheckManager;
use MirzaAghazadeh\SmartFailover\Services\DatabaseFailoverManager;
use MirzaAghazadeh\SmartFailover\Services\CacheFailoverManager;
use MirzaAghazadeh\SmartFailover\Services\QueueFailoverManager;
use MirzaAghazadeh\SmartFailover\Services\MailFailoverManager;
use MirzaAghazadeh\SmartFailover\Services\StorageFailoverManager;

class HealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'smart-failover:health 
                            {--service= : Check specific service (database, cache, queue, mail, storage)}
                            {--detailed : Show detailed health information}
                            {--json : Output in JSON format}';

    /**
     * The console command description.
     */
    protected $description = 'Check the health status of SmartFailover services';

    protected HealthCheckManager $healthManager;

    public function __construct(HealthCheckManager $healthManager)
    {
        parent::__construct();
        $this->healthManager = $healthManager;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $service = $this->option('service');
        $detailed = $this->option('detailed');
        $json = $this->option('json');

        try {
            if ($service) {
                $health = $this->checkSpecificService($service);
            } else {
                $health = $detailed 
                    ? $this->healthManager->checkAll()
                    : $this->healthManager->getHealthResponse();
            }

            if ($json) {
                $this->line(json_encode($health, JSON_PRETTY_PRINT));
                return 0;
            }

            $this->displayHealth($health, $detailed);
            
            // Return appropriate exit code
            return match ($health['status'] ?? 'unknown') {
                'healthy' => 0,
                'degraded' => 1,
                'unhealthy' => 2,
                default => 3,
            };

        } catch (\Exception $e) {
            $this->error('Health check failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Check specific service health
     */
    protected function checkSpecificService(string $service): array
    {
        return match ($service) {
            'database' => [
                'service' => 'database',
                'health' => app(DatabaseFailoverManager::class)->checkHealth(),
            ],
            'cache' => [
                'service' => 'cache',
                'health' => app(CacheFailoverManager::class)->checkHealth(),
            ],
            'queue' => [
                'service' => 'queue',
                'health' => app(QueueFailoverManager::class)->checkHealth(),
            ],
            'mail' => [
                'service' => 'mail',
                'health' => app(MailFailoverManager::class)->checkHealth(),
            ],
            'storage' => [
                'service' => 'storage',
                'health' => app(StorageFailoverManager::class)->checkHealth(),
            ],
            default => throw new \InvalidArgumentException("Unknown service: {$service}"),
        };
    }

    /**
     * Display health information
     */
    protected function displayHealth(array $health, bool $detailed = false): void
    {
        $status = $health['status'] ?? 'unknown';
        
        // Display overall status
        $this->displayStatus('Overall Status', $status);
        
        if (isset($health['services'])) {
            $this->newLine();
            $this->line('<comment>Service Health:</comment>');
            
            foreach ($health['services'] as $serviceName => $serviceHealth) {
                $this->displayServiceHealth($serviceName, $serviceHealth, $detailed);
            }
        } elseif (isset($health['service'])) {
            // Single service check
            $this->newLine();
            $this->displayServiceHealth($health['service'], $health['health'], $detailed);
        }

        if (isset($health['summary'])) {
            $this->newLine();
            $this->displaySummary($health['summary']);
        }
    }

    /**
     * Display service health
     */
    protected function displayServiceHealth(string $serviceName, array $serviceHealth, bool $detailed): void
    {
        $this->newLine();
        $this->line("<info>{$serviceName}:</info>");
        
        foreach ($serviceHealth as $instanceName => $instanceHealth) {
            $status = $instanceHealth['status'] ?? 'unknown';
            $responseTime = $instanceHealth['response_time'] ?? 'N/A';
            
            $statusColor = match ($status) {
                'healthy' => 'green',
                'degraded' => 'yellow',
                'unhealthy' => 'red',
                default => 'gray',
            };
            
            $this->line("  <fg={$statusColor}>●</fg> {$instanceName}: <fg={$statusColor}>{$status}</fg> ({$responseTime}ms)");
            
            if ($detailed && isset($instanceHealth['error'])) {
                $this->line("    <fg=red>Error:</fg> {$instanceHealth['error']}");
            }
            
            if ($detailed && isset($instanceHealth['last_checked'])) {
                $this->line("    <fg=gray>Last checked:</fg> {$instanceHealth['last_checked']}");
            }
        }
    }

    /**
     * Display status with appropriate color
     */
    protected function displayStatus(string $label, string $status): void
    {
        $statusColor = match ($status) {
            'healthy' => 'green',
            'degraded' => 'yellow',
            'unhealthy' => 'red',
            default => 'gray',
        };
        
        $statusIcon = match ($status) {
            'healthy' => '✓',
            'degraded' => '⚠',
            'unhealthy' => '✗',
            default => '?',
        };
        
        $this->line("<info>{$label}:</info> <fg={$statusColor}>{$statusIcon} {$status}</fg>");
    }

    /**
     * Display summary information
     */
    protected function displaySummary(array $summary): void
    {
        $this->line('<comment>Summary:</comment>');
        
        if (isset($summary['total_services'])) {
            $this->line("  Total services: {$summary['total_services']}");
        }
        
        if (isset($summary['healthy_services'])) {
            $this->line("  <fg=green>Healthy:</fg> {$summary['healthy_services']}");
        }
        
        if (isset($summary['degraded_services'])) {
            $this->line("  <fg=yellow>Degraded:</fg> {$summary['degraded_services']}");
        }
        
        if (isset($summary['unhealthy_services'])) {
            $this->line("  <fg=red>Unhealthy:</fg> {$summary['unhealthy_services']}");
        }
        
        if (isset($summary['average_response_time'])) {
            $this->line("  Average response time: {$summary['average_response_time']}ms");
        }
    }
}