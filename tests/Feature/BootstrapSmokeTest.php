<?php

namespace Tests\Feature;

use Tests\TestCase;

class BootstrapSmokeTest extends TestCase
{
    public function test_health_check_route_is_registered(): void
    {
        // Laravel's built-in /up health endpoint (registered via `health: '/up'`)
        $response = $this->get('/up');
        $response->assertStatus(200);
    }

    public function test_api_prefix_is_v1(): void
    {
        // Plan 01-02 replaced the install:api default /user route with /user/me.
        // Without a token it must return 401 JSON (Sanctum guard + ForceJsonResponse),
        // proving the /api/v1 prefix is active and middleware stack is wired correctly.
        $response = $this->getJson('/api/v1/user/me');
        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_user_model_has_api_tokens_trait(): void
    {
        $traits = class_uses_recursive(\App\Models\User::class);
        $this->assertContains(
            \Laravel\Sanctum\HasApiTokens::class,
            $traits,
            'User model must use the HasApiTokens trait.'
        );
    }
}
