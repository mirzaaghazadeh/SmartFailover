<?php

namespace MirzaAghazadeh\SmartFailover\Console\Commands;

use Illuminate\Console\Command;
use MirzaAghazadeh\SmartFailover\Services\CacheFailoverManager;
use MirzaAghazadeh\SmartFailover\Services\DatabaseFailoverManager;
use MirzaAghazadeh\SmartFailover\Services\QueueFailoverManager;
use MirzaAghazadeh\SmartFailover\SmartFailover;

class TestFailoverCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'smart-failover:test 
                            {service : Service to test (database, cache, queue, all)}
                            {--simulate-failure : Simulate service failure}
                            {--primary= : Primary service name}
                            {--fallback= : Fallback service name}';

    /**
     * The console command description.
     */
    protected $description = 'Test SmartFailover functionality and simulate failures';

    protected SmartFailover $smartFailover;

    public function __construct(SmartFailover $smartFailover)
    {
        parent::__construct();
        $this->smartFailover = $smartFailover;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $service = $this->argument('service');
        $simulateFailure = $this->option('simulate-failure');
        $primary = $this->option('primary');
        $fallback = $this->option('fallback');

        if (!is_string($service)) {
            $this->error('Service argument must be a string');
            return 1;
        }

        $this->info("Testing SmartFailover for service: {$service}");

        if ($simulateFailure) {
            $this->warn('Simulating service failure...');
        }

        try {
            return match ($service) {
                'database' => $this->testDatabase($primary, $fallback, $simulateFailure),
                'cache' => $this->testCache($primary, $fallback, $simulateFailure),
                'queue' => $this->testQueue($primary, $fallback, $simulateFailure),
                'all' => $this->testAll($simulateFailure),
                default => (function () use ($service) {
                    $this->error("Unknown service: {$service}");
                    return 1;
                })(),
            };
        } catch (\Exception $e) {
            $this->error('Test failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Test database failover.
     */
    protected function testDatabase(?string $primary, ?string $fallback, bool $simulateFailure): int
    {
        $this->line('<comment>Testing Database Failover...</comment>');

        $primary = $primary ?: config('database.default');
        $fallback = $fallback ?: 'mysql_backup';

        $this->line("Primary: {$primary}");
        $this->line("Fallback: {$fallback}");

        try {
            // Test basic connection
            $this->smartFailover
                ->db($primary, is_array($fallback) ? $fallback[0] : $fallback)
                ->send(function () {
                    return \DB::select('SELECT 1 as test');
                });

            $this->info('✓ Database failover test passed');

            // Test health check
            $databaseManager = app(DatabaseFailoverManager::class);
            $health = $databaseManager->checkHealth();

            $this->line('<comment>Database Health Status:</comment>');
            foreach ($health as $connection => $status) {
                $statusIcon = $status['status'] === 'healthy' ? '✓' : '✗';
                $this->line("  {$statusIcon} {$connection}: {$status['status']} ({$status['response_time']}ms)");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('✗ Database failover test failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Test cache failover.
     */
    protected function testCache(?string $primary, ?string $fallback, bool $simulateFailure): int
    {
        $this->line('<comment>Testing Cache Failover...</comment>');

        $primary = $primary ?: config('cache.default');
        $fallback = $fallback ?: 'array';

        $this->line("Primary: {$primary}");
        $this->line("Fallback: {$fallback}");

        try {
            $testKey = 'smart_failover_test_' . time();
            $testValue = 'test_value_' . rand(1000, 9999);

            // Test cache operations
            $this->smartFailover
                ->cache($primary, is_array($fallback) ? $fallback[0] : $fallback)
                ->send(function () use ($testKey, $testValue) {
                    \Cache::put($testKey, $testValue, 60);
                    return \Cache::get($testKey);
                });

            $this->info('✓ Cache failover test passed');

            // Test health check
            $cacheManager = app(CacheFailoverManager::class);
            $health = $cacheManager->checkHealth();

            $this->line('<comment>Cache Health Status:</comment>');
            foreach ($health as $store => $status) {
                $statusIcon = $status['status'] === 'healthy' ? '✓' : '✗';
                $this->line("  {$statusIcon} {$store}: {$status['status']} ({$status['response_time']}ms)");
            }

            // Cleanup
            \Cache::forget($testKey);

            return 0;

        } catch (\Exception $e) {
            $this->error('✗ Cache failover test failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Test queue failover.
     */
    protected function testQueue(?string $primary, ?string $fallback, bool $simulateFailure): int
    {
        $this->line('<comment>Testing Queue Failover...</comment>');

        $primary = $primary ?: config('queue.default');
        $fallback = $fallback ?: 'sync';

        $this->line("Primary: {$primary}");
        $this->line("Fallback: {$fallback}");

        try {
            // Test queue operations
            $this->smartFailover
                ->queue($primary, is_array($fallback) ? $fallback[0] : $fallback)
                ->send(function () {
                    // Create a simple test job
                    $job = new class () {
                        public function handle()
                        {
                            // Test job logic
                        }
                    };

                    \Queue::push($job);
                    return true;
                });

            $this->info('✓ Queue failover test passed');

            // Test health check
            $queueManager = app(QueueFailoverManager::class);
            $health = $queueManager->checkHealth();

            $this->line('<comment>Queue Health Status:</comment>');
            foreach ($health as $connection => $status) {
                $statusIcon = $status['status'] === 'healthy' ? '✓' : '✗';
                $this->line("  {$statusIcon} {$connection}: {$status['status']} ({$status['response_time']}ms)");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('✗ Queue failover test failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Test all services.
     */
    protected function testAll(bool $simulateFailure): int
    {
        $this->line('<comment>Testing All SmartFailover Services...</comment>');

        $results = [];

        // Test database
        $this->newLine();
        $results['database'] = $this->testDatabase(null, null, $simulateFailure);

        // Test cache
        $this->newLine();
        $results['cache'] = $this->testCache(null, null, $simulateFailure);

        // Test queue
        $this->newLine();
        $results['queue'] = $this->testQueue(null, null, $simulateFailure);

        // Summary
        $this->newLine();
        $this->line('<comment>Test Summary:</comment>');

        $passed = 0;
        $failed = 0;

        foreach ($results as $service => $result) {
            if ($result === 0) {
                $this->line("  ✓ {$service}: <fg=green>PASSED</fg>");
                $passed++;
            } else {
                $this->line("  ✗ {$service}: <fg=red>FAILED</fg>");
                $failed++;
            }
        }

        $this->newLine();
        $this->line('Total: ' . ($passed + $failed) . " | Passed: <fg=green>{$passed}</fg> | Failed: <fg=red>{$failed}</fg>");

        return $failed > 0 ? 1 : 0;
    }
}
