<?php

namespace Tests\Feature;

use Tests\TestCase;

class ErrorFormatTest extends TestCase
{
    public function test_404_returns_json_message_only(): void
    {
        $response = $this->getJson('/api/v1/nonexistent-route-xyz');

        $response->assertStatus(404);
        $response->assertJsonStructure(['message']);
        $response->assertJsonMissing(['errors' => []]);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $response->getContent());
    }

    public function test_401_returns_json_message_on_protected_route(): void
    {
        // /api/v1/user/me is auth:sanctum-protected per apis.md
        $response = $this->getJson('/api/v1/user/me');

        $response->assertStatus(401);
        $response->assertJsonStructure(['message']);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $response->getContent());
    }

    public function test_non_json_accept_header_still_returns_json_error(): void
    {
        // Deliberately omit Accept: application/json — ForceJsonResponse middleware must still deliver JSON
        $response = $this->get('/api/v1/nonexistent-route-xyz', ['Accept' => 'text/html']);

        $response->assertStatus(404);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $response->getContent());
        $this->assertJson($response->getContent());
    }
}
