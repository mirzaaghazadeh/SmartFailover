<?php

namespace Mirzaaghazadeh\SmartFailover\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \Mirzaaghazadeh\SmartFailover\SmartFailover database(string $connection = null)
 * @method static \Mirzaaghazadeh\SmartFailover\SmartFailover cache(string $store = null)
 * @method static \Mirzaaghazadeh\SmartFailover\SmartFailover queue(string $connection = null)
 * @method static \Mirzaaghazadeh\SmartFailover\SmartFailover mail(string $mailer = null)
 * @method static \Mirzaaghazadeh\SmartFailover\SmartFailover storage(string $disk = null)
 * @method static array getHealthStatus()
 * @method static void enableService(string $service, string $connection = null)
 * @method static void disableService(string $service, string $connection = null)
 * @method static bool isServiceEnabled(string $service, string $connection = null)
 * @method static array getFailoverHistory(string $service = null, int $limit = 100)
 * @method static void clearFailoverHistory(string $service = null)
 * @method static array getServiceMetrics(string $service = null)
 * @method static void resetServiceMetrics(string $service = null)
 *
 * @see \Mirzaaghazadeh\SmartFailover\SmartFailover
 */
class SmartFailover extends Facade
{
    /**
     * Get the registered name of the component.
     */
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return 'smart-failover';
    }
}
