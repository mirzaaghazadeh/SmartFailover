<?php

use Illuminate\Support\Facades\Route;
use Mirzaaghazadeh\SmartFailover\Http\Controllers\HealthController;

/*
|--------------------------------------------------------------------------
| SmartFailover Health Check Routes
|--------------------------------------------------------------------------
|
| These routes provide health check endpoints for monitoring the status
| of all configured failover services including database, cache, queue,
| storage, and mail services.
|
*/

$routePath = config('smart-failover.health_check.route_path', '/health/smart-failover');
$middleware = config('smart-failover.health_check.middleware', ['web']);

Route::middleware($middleware)->group(function () use ($routePath) {
    // Main health check endpoint
    Route::get($routePath, [HealthController::class, 'index'])
        ->name('smart-failover.health');
    
    // Detailed health check with service breakdown
    Route::get($routePath . '/detailed', [HealthController::class, 'detailed'])
        ->name('smart-failover.health.detailed');
    
    // Individual service health checks
    Route::get($routePath . '/database', [HealthController::class, 'database'])
        ->name('smart-failover.health.database');
    
    Route::get($routePath . '/cache', [HealthController::class, 'cache'])
        ->name('smart-failover.health.cache');
    
    Route::get($routePath . '/queue', [HealthController::class, 'queue'])
        ->name('smart-failover.health.queue');
    
    Route::get($routePath . '/storage', [HealthController::class, 'storage'])
        ->name('smart-failover.health.storage');
    
    Route::get($routePath . '/mail', [HealthController::class, 'mail'])
        ->name('smart-failover.health.mail');
});