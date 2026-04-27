<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_returns_200_json(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
        $response->assertHeader('content-type', 'application/json');
    }

    public function test_health_response_is_not_html(): void
    {
        $response = $this->getJson('/api/v1/health');

        $this->assertStringNotContainsString('<!DOCTYPE html>', $response->getContent());
        $this->assertStringNotContainsString('<html', $response->getContent());
    }
}
