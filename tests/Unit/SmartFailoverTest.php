<?php

namespace MirzaAghazadeh\SmartFailover\Tests\Unit;

use MirzaAghazadeh\SmartFailover\Tests\TestCase;
use MirzaAghazadeh\SmartFailover\SmartFailover;

class SmartFailoverTest extends TestCase
{
    protected SmartFailover $smartFailover;

    protected function setUp(): void
    {
        parent::setUp();
        $this->smartFailover = app(SmartFailover::class);
    }

    /** @test */
    public function it_can_configure_database_failover()
    {
        $result = $this->smartFailover->db('mysql_primary', 'mysql_backup');
        
        $this->assertInstanceOf(SmartFailover::class, $result);
    }

    /** @test */
    public function it_can_configure_cache_failover()
    {
        $result = $this->smartFailover->cache('redis', 'memcached');
        
        $this->assertInstanceOf(SmartFailover::class, $result);
    }

    /** @test */
    public function it_can_configure_queue_failover()
    {
        $result = $this->smartFailover->queue('sqs', 'redis');
        
        $this->assertInstanceOf(SmartFailover::class, $result);
    }

    /** @test */
    public function it_can_chain_multiple_configurations()
    {
        $result = $this->smartFailover
            ->db('mysql_primary', 'mysql_backup')
            ->cache('redis', 'memcached')
            ->queue('sqs', 'redis');
        
        $this->assertInstanceOf(SmartFailover::class, $result);
    }

    /** @test */
    public function it_can_execute_operations_with_send_method()
    {
        $executed = false;
        
        $this->smartFailover->send(function() use (&$executed) {
            $executed = true;
            return 'test result';
        });
        
        $this->assertTrue($executed);
    }

    /** @test */
    public function it_can_get_health_status()
    {
        $health = $this->smartFailover->health();
        
        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
    }

    /** @test */
    public function it_can_reset_configuration()
    {
        $this->smartFailover
            ->db('mysql_primary', 'mysql_backup')
            ->cache('redis', 'memcached');
        
        $result = $this->smartFailover->reset();
        
        $this->assertInstanceOf(SmartFailover::class, $result);
    }
}