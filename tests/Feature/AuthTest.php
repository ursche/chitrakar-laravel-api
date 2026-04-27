<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // AUTH-01: Registration
    // -------------------------------------------------------------------------

    public function test_register_creates_user_with_five_credits_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Aarav Sharma',
            'email'                 => 'aarav@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'user' => ['id', 'name', 'email', 'credits', 'created_at'],
                     'token',
                 ])
                 ->assertJsonPath('user.credits', 5);

        $this->assertDatabaseHas('users', [
            'email'   => 'aarav@example.com',
            'credits' => 5,
        ]);
    }

    public function test_register_returns_422_on_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dupe@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Another User',
            'email'                 => 'dupe@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonStructure(['errors' => ['email']]);
    }

    // -------------------------------------------------------------------------
    // AUTH-02: Login
    // -------------------------------------------------------------------------

    public function test_login_returns_token_on_valid_credentials(): void
    {
        User::factory()->create([
            'email'    => 'login@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'login@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'user',
                     'token',
                 ]);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_returns_401_on_invalid_credentials(): void
    {
        User::factory()->create([
            'email'    => 'user@example.com',
            'password' => Hash::make('correct'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
                 ->assertJsonStructure(['message']);
    }

    public function test_login_returns_401_on_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'ghost@example.com',
            'password' => 'anything',
        ]);

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // AUTH-03: Logout (RED in Plan 01 — goes GREEN in Plan 02)
    // -------------------------------------------------------------------------

    public function test_logout_revokes_token_and_subsequent_request_returns_401(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->postJson('/api/v1/auth/logout')
             ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->getJson('/api/v1/user/me')
             ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // AUTH-04: Forgot Password (RED in Plan 01 — goes GREEN in Plan 02)
    // -------------------------------------------------------------------------

    public function test_forgot_password_returns_200_for_known_email(): void
    {
        User::factory()->create(['email' => 'known@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'known@example.com',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message']);
    }

    public function test_forgot_password_returns_200_for_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message']);
    }

    // -------------------------------------------------------------------------
    // AUTH-05: Reset Password (RED in Plan 01 — goes GREEN in Plan 02)
    // -------------------------------------------------------------------------

    public function test_reset_password_updates_user_password(): void
    {
        $user  = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message']);

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_reset_password_returns_422_on_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => 'invalid-token-string',
            'email'                 => $user->email,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonStructure(['errors' => ['email']]);
    }
}
