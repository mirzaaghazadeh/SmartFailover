<?php

namespace Mirzaaghazadeh\SmartFailover\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Mirzaaghazadeh\SmartFailover\SmartFailover db(string $primary, string $fallback = null)
 * @method static \Mirzaaghazadeh\SmartFailover\SmartFailover cache(string $primary, string $fallback = null)
 * @method static \Mirzaaghazadeh\SmartFailover\SmartFailover queue(string $primary, string $fallback = null)
 * @method static mixed send(\Closure $callback)
 * @method static mixed database(\Closure $callback)
 * @method static mixed cacheOperation(\Closure $callback)
 * @method static mixed queueOperation(\Closure $callback)
 * @method static array getHealthStatus()
 * @method static bool isHealthy(string $service)
 * @method static \Mirzaaghazadeh\SmartFailover\SmartFailover reset()
 *
 * @see \Mirzaaghazadeh\SmartFailover\SmartFailover
 */
class SmartFailover extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Mirzaaghazadeh\SmartFailover\SmartFailover::class;
    }
}
