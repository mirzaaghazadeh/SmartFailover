final <?php

namespace Mirzaaghazadeh\SmartFailover\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;
use Mirzaaghazadeh\SmartFailover\Services\CacheFailoverManager;
use Mirzaaghazadeh\SmartFailover\Services\DatabaseFailoverManager;
use Mirzaaghazadeh\SmartFailover\Services\HealthCheckManager;
use Mirzaaghazadeh\SmartFailover\Services\QueueFailoverManager;

class HealthController extends Controller
{
    protected HealthCheckManager $healthManager;

    public function __construct(HealthCheckManager $healthManager)
    {
        $this->healthManager = $healthManager;
    }

    /**
     * Get overall health status.
     */
    public function index(): JsonResponse
    {
        try {
            $health = $this->healthManager->getHealthResponse();

            $statusCode = match ($health['status']) {
                'healthy' => 200,
                'degraded' => 206, // Partial Content
                'unhealthy' => 503, // Service Unavailable
                default => 200,
            };

            return Response::json($health, $statusCode);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Health check failed',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Get detailed health status.
     */
    public function detailed(): JsonResponse
    {
        try {
            $health = $this->healthManager->checkAll();

            $statusCode = match ($health['status']) {
                'healthy' => 200,
                'degraded' => 206,
                'unhealthy' => 503,
                default => 200,
            };

            return response()->json($health, $statusCode);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Detailed health check failed',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Get database health status.
     */
    public function database(): JsonResponse
    {
        try {
            $databaseManager = app(DatabaseFailoverManager::class);
            $health = $databaseManager->checkHealth();

            $allHealthy = collect($health)->every(fn ($service) => $service['status'] === 'healthy');
            $statusCode = $allHealthy ? 200 : 503;

            return response()->json([
                'service' => 'database',
                'status' => $allHealthy ? 'healthy' : 'unhealthy',
                'connections' => $health,
                'timestamp' => now()->toISOString(),
            ], $statusCode);
        } catch (\Exception $e) {
            return response()->json([
                'service' => 'database',
                'status' => 'error',
                'message' => 'Database health check failed',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Get cache health status.
     */
    public function cache(): JsonResponse
    {
        try {
            $cacheManager = app(CacheFailoverManager::class);
            $health = $cacheManager->checkHealth();

            $allHealthy = collect($health)->every(fn ($service) => $service['status'] === 'healthy');
            $statusCode = $allHealthy ? 200 : 503;

            return response()->json([
                'service' => 'cache',
                'status' => $allHealthy ? 'healthy' : 'unhealthy',
                'stores' => $health,
                'timestamp' => now()->toISOString(),
            ], $statusCode);
        } catch (\Exception $e) {
            return response()->json([
                'service' => 'cache',
                'status' => 'error',
                'message' => 'Cache health check failed',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Get queue health status.
     */
    public function queue(): JsonResponse
    {
        try {
            $queueManager = app(QueueFailoverManager::class);
            $health = $queueManager->checkHealth();

            $allHealthy = collect($health)->every(fn ($service) => $service['status'] === 'healthy');
            $statusCode = $allHealthy ? 200 : 503;

            return response()->json([
                'service' => 'queue',
                'status' => $allHealthy ? 'healthy' : 'unhealthy',
                'connections' => $health,
                'timestamp' => now()->toISOString(),
            ], $statusCode);
        } catch (\Exception $e) {
            return response()->json([
                'service' => 'queue',
                'status' => 'error',
                'message' => 'Queue health check failed',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Get storage health status.
     */
    public function storage(): JsonResponse
    {
        try {
            $health = $this->healthManager->checkAll()['services']['storage'] ?? [];

            $allHealthy = collect($health)->every(fn ($service) => $service['status'] === 'healthy');
            $statusCode = $allHealthy ? 200 : 503;

            return response()->json([
                'service' => 'storage',
                'status' => $allHealthy ? 'healthy' : 'unhealthy',
                'disks' => $health,
                'timestamp' => now()->toISOString(),
            ], $statusCode);
        } catch (\Exception $e) {
            return response()->json([
                'service' => 'storage',
                'status' => 'error',
                'message' => 'Storage health check failed',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Get mail health status.
     */
    public function mail(): JsonResponse
    {
        try {
            $health = $this->healthManager->checkAll()['services']['mail'] ?? [];

            $allHealthy = collect($health)->every(fn ($service) => $service['status'] === 'healthy');
            $statusCode = $allHealthy ? 200 : 503;

            return response()->json([
                'service' => 'mail',
                'status' => $allHealthy ? 'healthy' : 'unhealthy',
                'mailers' => $health,
                'timestamp' => now()->toISOString(),
            ], $statusCode);
        } catch (\Exception $e) {
            return response()->json([
                'service' => 'mail',
                'status' => 'error',
                'message' => 'Mail health check failed',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }
}
