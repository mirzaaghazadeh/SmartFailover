<?php

namespace MirzaAghazadeh\SmartFailover\Services;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;
use Exception;

class StorageFailoverManager
{
    protected Config $config;
    protected LoggerInterface $logger;
    protected FilesystemManager $storageManager;
    protected array $disks = [];
    protected ?string $primaryDisk = null;
    protected array $fallbackDisks = [];
    protected array $healthStatus = [];
    protected int $retryAttempts = 3;
    protected int $retryDelay = 1000; // milliseconds

    public function __construct(Config $config, LoggerInterface $logger, FilesystemManager $storageManager)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->storageManager = $storageManager;
        $this->loadConfiguration();
    }

    /**
     * Set disks for failover
     */
    public function setDisks(string $primary, array $fallbacks = []): self
    {
        $this->primaryDisk = $primary;
        $this->fallbackDisks = $fallbacks;
        $this->disks = array_merge([$primary], $fallbacks);
        
        return $this;
    }

    /**
     * Store file with failover protection
     */
    public function put(string $path, $contents, array $options = []): bool
    {
        $disks = $this->getAvailableDisks();
        
        foreach ($disks as $diskName) {
            try {
                $this->logger->info("Attempting to store file on {$diskName}");
                
                $disk = $this->storageManager->disk($diskName);
                $result = $disk->put($path, $contents, $options);
                
                $this->markDiskHealthy($diskName);
                $this->logger->info("File stored successfully on {$diskName}");
                
                return $result;
                
            } catch (Exception $e) {
                $this->markDiskUnhealthy($diskName, $e->getMessage());
                $this->logger->error("Storage failed on {$diskName}: " . $e->getMessage());
                
                // Continue to next disk
                continue;
            }
        }
        
        $this->logger->error('All storage services failed');
        throw new Exception('All configured storage services are unavailable');
    }

    /**
     * Get file with failover protection
     */
    public function get(string $path): ?string
    {
        $disks = $this->getAvailableDisks();
        
        foreach ($disks as $diskName) {
            try {
                $this->logger->info("Attempting to get file from {$diskName}");
                
                $disk = $this->storageManager->disk($diskName);
                
                if ($disk->exists($path)) {
                    $contents = $disk->get($path);
                    $this->markDiskHealthy($diskName);
                    $this->logger->info("File retrieved successfully from {$diskName}");
                    
                    return $contents;
                }
                
            } catch (Exception $e) {
                $this->markDiskUnhealthy($diskName, $e->getMessage());
                $this->logger->error("Storage retrieval failed on {$diskName}: " . $e->getMessage());
                
                // Continue to next disk
                continue;
            }
        }
        
        $this->logger->warning("File not found on any storage service: {$path}");
        return null;
    }

    /**
     * Delete file with failover protection
     */
    public function delete(string $path): bool
    {
        $disks = $this->getAvailableDisks();
        $success = false;
        
        foreach ($disks as $diskName) {
            try {
                $this->logger->info("Attempting to delete file from {$diskName}");
                
                $disk = $this->storageManager->disk($diskName);
                
                if ($disk->exists($path)) {
                    $result = $disk->delete($path);
                    if ($result) {
                        $success = true;
                        $this->logger->info("File deleted successfully from {$diskName}");
                    }
                }
                
                $this->markDiskHealthy($diskName);
                
            } catch (Exception $e) {
                $this->markDiskUnhealthy($diskName, $e->getMessage());
                $this->logger->error("Storage deletion failed on {$diskName}: " . $e->getMessage());
            }
        }
        
        return $success;
    }

    /**
     * Check if file exists with failover protection
     */
    public function exists(string $path): bool
    {
        $disks = $this->getAvailableDisks();
        
        foreach ($disks as $diskName) {
            try {
                $disk = $this->storageManager->disk($diskName);
                
                if ($disk->exists($path)) {
                    $this->markDiskHealthy($diskName);
                    return true;
                }
                
            } catch (Exception $e) {
                $this->markDiskUnhealthy($diskName, $e->getMessage());
                $this->logger->error("Storage check failed on {$diskName}: " . $e->getMessage());
            }
        }
        
        return false;
    }

    /**
     * Get file URL with failover protection
     */
    public function url(string $path): ?string
    {
        $disks = $this->getAvailableDisks();
        
        foreach ($disks as $diskName) {
            try {
                $disk = $this->storageManager->disk($diskName);
                
                if ($disk->exists($path)) {
                    $url = $disk->url($path);
                    $this->markDiskHealthy($diskName);
                    return $url;
                }
                
            } catch (Exception $e) {
                $this->markDiskUnhealthy($diskName, $e->getMessage());
                $this->logger->error("Storage URL generation failed on {$diskName}: " . $e->getMessage());
            }
        }
        
        return null;
    }

    /**
     * Check health of all configured disks
     */
    public function checkHealth(): array
    {
        $health = [];
        
        foreach ($this->disks as $diskName) {
            $health[$diskName] = $this->checkDiskHealth($diskName);
        }
        
        return $health;
    }

    /**
     * Check health of a specific disk
     */
    protected function checkDiskHealth(string $diskName): array
    {
        $startTime = microtime(true);
        
        try {
            $disk = $this->storageManager->disk($diskName);
            
            // Test basic operations
            $testFile = 'health-check-' . time() . '.txt';
            $testContent = 'SmartFailover health check';
            
            // Test write
            $disk->put($testFile, $testContent);
            
            // Test read
            $retrievedContent = $disk->get($testFile);
            
            // Test delete
            $disk->delete($testFile);
            
            // Verify content matches
            if ($retrievedContent !== $testContent) {
                throw new Exception('Content verification failed');
            }
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'status' => 'healthy',
                'response_time' => $responseTime,
                'driver' => $this->config->get("filesystems.disks.{$diskName}.driver", 'unknown'),
                'last_checked' => now()->toISOString(),
            ];
            
        } catch (Exception $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'status' => 'unhealthy',
                'response_time' => $responseTime,
                'error' => $e->getMessage(),
                'last_checked' => now()->toISOString(),
            ];
        }
    }

    /**
     * Get available disks in priority order
     */
    protected function getAvailableDisks(): array
    {
        $available = [];
        
        // Add primary disk if healthy
        if ($this->primaryDisk && $this->isDiskHealthy($this->primaryDisk)) {
            $available[] = $this->primaryDisk;
        }
        
        // Add healthy fallback disks
        foreach ($this->fallbackDisks as $disk) {
            if ($this->isDiskHealthy($disk)) {
                $available[] = $disk;
            }
        }
        
        // If no healthy disks, try all configured disks
        if (empty($available)) {
            $available = $this->disks;
        }
        
        return $available;
    }

    /**
     * Check if disk is healthy
     */
    protected function isDiskHealthy(string $diskName): bool
    {
        if (!isset($this->healthStatus[$diskName])) {
            return true; // Assume healthy if not checked yet
        }
        
        $status = $this->healthStatus[$diskName];
        
        // Consider unhealthy if last failure was recent (within 5 minutes)
        if ($status['status'] === 'unhealthy') {
            $lastFailure = $status['last_failure'] ?? 0;
            return (time() - $lastFailure) > 300; // 5 minutes
        }
        
        return $status['status'] === 'healthy';
    }

    /**
     * Mark disk as healthy
     */
    protected function markDiskHealthy(string $diskName): void
    {
        $this->healthStatus[$diskName] = [
            'status' => 'healthy',
            'last_success' => time(),
        ];
    }

    /**
     * Mark disk as unhealthy
     */
    protected function markDiskUnhealthy(string $diskName, string $error): void
    {
        $this->healthStatus[$diskName] = [
            'status' => 'unhealthy',
            'last_failure' => time(),
            'error' => $error,
        ];
    }

    /**
     * Load configuration
     */
    protected function loadConfiguration(): void
    {
        $config = $this->config->get('smart-failover.storage', []);
        
        if (isset($config['primary'])) {
            $this->primaryDisk = $config['primary'];
        }
        
        if (isset($config['fallbacks'])) {
            $this->fallbackDisks = $config['fallbacks'];
        }
        
        if (isset($config['retry_attempts'])) {
            $this->retryAttempts = $config['retry_attempts'];
        }
        
        if (isset($config['retry_delay'])) {
            $this->retryDelay = $config['retry_delay'];
        }
        
        $this->disks = array_merge(
            $this->primaryDisk ? [$this->primaryDisk] : [],
            $this->fallbackDisks
        );
    }

    /**
     * Get current storage status
     */
    public function getStatus(): array
    {
        return [
            'primary' => $this->primaryDisk,
            'fallbacks' => $this->fallbackDisks,
            'health' => $this->healthStatus,
            'available_disks' => $this->getAvailableDisks(),
        ];
    }

    /**
     * Sync file across all available disks
     */
    public function sync(string $path, $contents = null): array
    {
        $results = [];
        $disks = $this->getAvailableDisks();
        
        // If no contents provided, get from primary disk
        if ($contents === null && $this->primaryDisk) {
            try {
                $contents = $this->storageManager->disk($this->primaryDisk)->get($path);
            } catch (Exception $e) {
                throw new Exception("Could not retrieve file for syncing: " . $e->getMessage());
            }
        }
        
        foreach ($disks as $diskName) {
            try {
                $disk = $this->storageManager->disk($diskName);
                $result = $disk->put($path, $contents);
                $results[$diskName] = $result ? 'success' : 'failed';
                
                if ($result) {
                    $this->markDiskHealthy($diskName);
                }
                
            } catch (Exception $e) {
                $results[$diskName] = 'error: ' . $e->getMessage();
                $this->markDiskUnhealthy($diskName, $e->getMessage());
            }
        }
        
        return $results;
    }
}