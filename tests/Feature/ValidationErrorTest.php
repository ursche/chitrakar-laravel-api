<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ValidationErrorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register a throwaway route for validation contract testing. This route is only
        // registered inside this test — it does not pollute real routes/api.php.
        Route::post('/api/v1/_test/validate', function (\Illuminate\Http\Request $request) {
            $request->validate([
                'name' => 'required|string',
                'email' => 'required|email',
            ]);
            return response()->json(['ok' => true]);
        });
    }

    public function test_422_response_matches_err_01_contract(): void
    {
        $response = $this->postJson('/api/v1/_test/validate', []);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'name',
                'email',
            ],
        ]);

        // errors.name and errors.email must each be an array of strings (Laravel default)
        $body = $response->json();
        $this->assertIsArray($body['errors']['name']);
        $this->assertIsArray($body['errors']['email']);
        $this->assertIsString($body['errors']['name'][0]);
    }

    public function test_422_response_is_json_not_html(): void
    {
        $response = $this->postJson('/api/v1/_test/validate', []);

        $this->assertStringNotContainsString('<!DOCTYPE html>', $response->getContent());
        $this->assertJson($response->getContent());
    }
}
