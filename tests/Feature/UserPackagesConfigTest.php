<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserPackagesConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(PackageSeeder::class)) {
            $this->seed(PackageSeeder::class);
        }
    }

    /**
     * Authenticate the given user and return the test instance with the Bearer header set.
     */
    private function actingAsUser(User $user): static
    {
        $token = $user->createToken('test')->plainTextToken;
        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    // -------------------------------------------------------------------------
    // USER-01: Get authenticated user profile
    // -------------------------------------------------------------------------

    public function test_get_user_me_returns_profile_with_credits(): void
    {
        $user = User::factory()->create(['credits' => 25]);

        $response = $this->actingAsUser($user)->getJson('/api/v1/user/me');

        $response->assertStatus(200)
                 ->assertJsonStructure(['id', 'name', 'email', 'credits', 'created_at'])
                 ->assertJsonPath('credits', 25)
                 ->assertJsonMissing(['password', 'token', 'user']);
    }

    public function test_get_user_me_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/user/me');

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // USER-02: Update authenticated user profile
    // -------------------------------------------------------------------------

    public function test_update_user_me_updates_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsUser($user)->putJson('/api/v1/user/me', ['name' => 'New Name']);

        $response->assertStatus(200)
                 ->assertJsonPath('name', 'New Name');

        $this->assertDatabaseHas('users', [
            'id'   => $user->id,
            'name' => 'New Name',
        ]);
    }

    public function test_update_user_me_updates_email(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsUser($user)->putJson('/api/v1/user/me', ['email' => 'new@example.com']);

        $response->assertStatus(200)
                 ->assertJsonPath('email', 'new@example.com');

        $this->assertDatabaseHas('users', [
            'id'    => $user->id,
            'email' => 'new@example.com',
        ]);
    }

    public function test_update_user_me_rejects_taken_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $response = $this->actingAsUser($user)->putJson('/api/v1/user/me', ['email' => 'taken@example.com']);

        $response->assertStatus(422)
                 ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_update_user_me_allows_keeping_own_email(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);

        $response = $this->actingAsUser($user)->putJson('/api/v1/user/me', ['email' => 'me@example.com']);

        $response->assertStatus(200)
                 ->assertJsonPath('email', 'me@example.com');
    }

    public function test_update_user_me_does_not_allow_credits_change(): void
    {
        $user = User::factory()->create(['credits' => 10]);

        $response = $this->actingAsUser($user)->putJson('/api/v1/user/me', ['credits' => 99999]);

        $response->assertStatus(200)
                 ->assertJsonPath('credits', 10);

        $this->assertDatabaseHas('users', [
            'id'      => $user->id,
            'credits' => 10,
        ]);
    }

    public function test_update_user_me_requires_auth(): void
    {
        $response = $this->putJson('/api/v1/user/me', ['name' => 'x']);

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // PKG-01: Public packages listing (will remain red until Plan 02)
    // -------------------------------------------------------------------------

    public function test_packages_returns_four_packages_with_correct_shape(): void
    {
        $response = $this->getJson('/api/v1/packages');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         ['id', 'name', 'price_npr', 'credits', 'popular'],
                     ],
                 ]);

        $this->assertCount(4, $response->json('data'));
    }

    public function test_packages_requires_no_auth(): void
    {
        $response = $this->getJson('/api/v1/packages');

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // CFG-01: Public config/options (will remain red until Plan 02)
    // -------------------------------------------------------------------------

    public function test_config_options_returns_required_keys(): void
    {
        $response = $this->getJson('/api/v1/config/options');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'model_styles',
                     'settings',
                     'aesthetics',
                     'themes',
                     'service_credits',
                 ]);
    }

    public function test_config_options_includes_service_credits(): void
    {
        $response = $this->getJson('/api/v1/config/options');

        $response->assertStatus(200)
                 ->assertJsonPath('service_credits.virtual-mannequin', 4)
                 ->assertJsonPath('service_credits.product-staging', 3)
                 ->assertJsonPath('service_credits.promotional-banner', 2);
    }

    public function test_config_options_requires_no_auth(): void
    {
        $response = $this->getJson('/api/v1/config/options');

        $response->assertStatus(200);
    }
}
