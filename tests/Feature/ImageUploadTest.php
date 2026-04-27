<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(User $user): static
    {
        $token = $user->createToken('test')->plainTextToken;
        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    // IMG-01: valid JPEG returns 201 with url, key, size_bytes, mime_type
    public function test_valid_jpeg_upload_returns_201(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('photo.jpg', 1024, 'image/jpeg');

        $response = $this->actingAsUser($user)
            ->post('/api/v1/upload', ['file' => $file]);

        $response->assertStatus(201)
            ->assertJsonStructure(['url', 'key', 'size_bytes', 'mime_type']);
        $this->assertStringContainsString('/storage/uploads/' . $user->id, $response->json('url'));
        $this->assertStringStartsWith('uploads/' . $user->id, $response->json('key'));
        $this->assertSame('image/jpeg', $response->json('mime_type'));
        Storage::disk('public')->assertExists($response->json('key'));
    }

    // IMG-01: valid PNG returns 201
    public function test_valid_png_upload_returns_201(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('photo.png', 500, 'image/png');

        $response = $this->actingAsUser($user)
            ->post('/api/v1/upload', ['file' => $file]);

        $response->assertStatus(201)
            ->assertJsonStructure(['url', 'key', 'size_bytes', 'mime_type']);
        $this->assertSame('image/png', $response->json('mime_type'));
        Storage::disk('public')->assertExists($response->json('key'));
    }

    // IMG-01: valid WEBP returns 201
    public function test_valid_webp_upload_returns_201(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('photo.webp', 2048, 'image/webp');

        $response = $this->actingAsUser($user)
            ->post('/api/v1/upload', ['file' => $file]);

        $response->assertStatus(201)
            ->assertJsonStructure(['url', 'key', 'size_bytes', 'mime_type']);
        $this->assertSame('image/webp', $response->json('mime_type'));
        Storage::disk('public')->assertExists($response->json('key'));
    }

    // IMG-02: wrong MIME type returns 422 with errors.file
    public function test_wrong_mime_type_returns_422(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAsUser($user)
            ->post('/api/v1/upload', ['file' => $file]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['file']]);
    }

    // IMG-02: file over 10MB returns 422 with errors.file
    public function test_oversized_file_returns_422(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('big.jpg', 11 * 1024, 'image/jpeg');

        $response = $this->actingAsUser($user)
            ->post('/api/v1/upload', ['file' => $file]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['file']]);
    }

    // IMG-01: unauthenticated upload returns 401
    public function test_upload_requires_auth(): void
    {
        $file = UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg');
        $response = $this->postJson('/api/v1/upload', ['file' => $file]);
        $response->assertStatus(401);
    }
}
