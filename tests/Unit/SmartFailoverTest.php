<?php

namespace Mirzaaghazadeh\SmartFailover\Tests\Unit;

use Mirzaaghazadeh\SmartFailover\SmartFailover;
use Mirzaaghazadeh\SmartFailover\Tests\TestCase;

class SmartFailoverTest extends TestCase
{
    protected SmartFailover $smartFailover;

    protected function setUp(): void
    {
        parent::setUp();
        $this->smartFailover = app(SmartFailover::class);
    }

    /** @test */
    public function it_can_configure_database_failover(): void
    {
        $result = $this->smartFailover->db('mysql_primary', 'mysql_backup');

        $this->assertInstanceOf(SmartFailover::class, $result);
    }

    /** @test */
    public function it_can_configure_cache_failover(): void
    {
        $result = $this->smartFailover->cache('redis', 'memcached');

        $this->assertInstanceOf(SmartFailover::class, $result);
    }

    /** @test */
    public function it_can_configure_queue_failover(): void
    {
        $result = $this->smartFailover->queue('sqs', 'redis');

        $this->assertInstanceOf(SmartFailover::class, $result);
    }

    /** @test */
    public function it_can_chain_multiple_configurations(): void
    {
        $result = $this->smartFailover
            ->db('mysql_primary', 'mysql_backup')
            ->cache('redis', 'memcached')
            ->queue('sqs', 'redis');

        $this->assertInstanceOf(SmartFailover::class, $result);
    }

    /** @test */
    public function it_can_execute_callback(): void
    {
        $executed = false;

        $this->smartFailover->send(function () use (&$executed) {
            $executed = true;
            return 'test result';
        });

        $this->assertTrue($executed);
    }

    /** @test */
    public function it_can_get_health_status(): void
    {
        $health = $this->smartFailover->health();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
    }

    /** @test */
    public function it_can_reset_configuration(): void
    {
        $this->smartFailover
            ->db('mysql_primary', 'mysql_backup')
            ->cache('redis', 'memcached');

        $result = $this->smartFailover->reset();

        $this->assertInstanceOf(SmartFailover::class, $result);
    }
}
