<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DynamicRequestRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'security.dynamic_requests.enabled' => true,
            'security.dynamic_requests.ip_attempts' => 3,
            'security.dynamic_requests.network_attempts' => 100,
            'security.dynamic_requests.window_seconds' => 60,
            'security.dynamic_requests.block_seconds' => 900,
        ]);
    }

    public function test_ip_is_blocked_for_fifteen_minutes_after_request_limit_is_exceeded(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->fromIp('203.0.113.10')->get('/login')->assertOk();
        }

        $this->fromIp('203.0.113.10')
            ->get('/login')
            ->assertStatus(429)
            ->assertHeader('Retry-After', '900');

        $this->travel(61)->seconds();

        $this->fromIp('203.0.113.10')
            ->get('/login')
            ->assertStatus(429);

        $this->travel(900)->seconds();

        $this->fromIp('203.0.113.10')->get('/login')->assertOk();
    }

    public function test_network_is_blocked_when_many_ips_in_same_network_exceed_limit(): void
    {
        config([
            'security.dynamic_requests.ip_attempts' => 100,
            'security.dynamic_requests.network_attempts' => 3,
        ]);

        $this->fromIp('198.51.100.10')->get('/login')->assertOk();
        $this->fromIp('198.51.100.11')->get('/login')->assertOk();
        $this->fromIp('198.51.100.12')->get('/login')->assertOk();

        $this->fromIp('198.51.100.13')
            ->get('/login')
            ->assertStatus(429)
            ->assertHeader('Retry-After', '900');
    }

    public function test_health_routes_are_excluded_from_dynamic_rate_limit(): void
    {
        config([
            'security.dynamic_requests.ip_attempts' => 1,
            'security.dynamic_requests.network_attempts' => 1,
        ]);

        $this->fromIp('192.0.2.50')->get('/health')->assertOk();
        $this->fromIp('192.0.2.50')->get('/health')->assertOk();
        $this->fromIp('192.0.2.50')->get('/up')->assertOk();
        $this->fromIp('192.0.2.50')->get('/up')->assertOk();
    }

    private function fromIp(string $ip): self
    {
        return $this->withServerVariables([
            'REMOTE_ADDR' => $ip,
        ]);
    }
}
