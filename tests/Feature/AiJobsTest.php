<?php

namespace Tests\Feature;

use App\Models\AiJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiJobsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(User $user): static
    {
        $token = $user->createToken('test')->plainTextToken;
        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function validPayload(string $serviceType = 'virtual-mannequin'): array
    {
        return [
            'service_type'    => $serviceType,
            'input_image_url' => 'https://example.com/image.jpg',
            'prompt_payload'  => ['model_style' => 'Traditional Nepali'],
        ];
    }

    // JOB-01 + JOB-03
    public function test_create_job_deducts_credits_and_returns_201(): void
    {
        $user = User::factory()->create(['credits' => 5]);

        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/jobs', $this->validPayload('virtual-mannequin'));

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'user_id', 'service_type', 'input_image_url',
                'prompt_payload', 'status', 'output_urls', 'credits_used',
                'created_at', 'completed_at',
            ]);

        $this->assertDatabaseHas('ai_jobs', [
            'user_id'      => $user->id,
            'service_type' => 'virtual-mannequin',
            'status'       => 'processing',
            'credits_used' => 4,
        ]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'credits' => 1]);
    }

    public function test_create_job_returns_201_response_shape(): void
    {
        $user = User::factory()->create(['credits' => 10]);

        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/jobs', $this->validPayload('virtual-mannequin'));

        $response->assertStatus(201);
        $this->assertSame('processing', $response->json('status'));
        $this->assertSame([], $response->json('output_urls'));
        $this->assertNull($response->json('completed_at'));
    }

    // JOB-02
    public function test_create_job_returns_402_when_insufficient_credits(): void
    {
        $user = User::factory()->create(['credits' => 3]);

        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/jobs', $this->validPayload('virtual-mannequin'));

        $response->assertStatus(402)
            ->assertJson(['message' => 'Insufficient credits.']);

        $this->assertDatabaseMissing('ai_jobs', ['user_id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'credits' => 3]);
    }

    // JOB-03
    public function test_credit_costs_are_correct_per_service_type(): void
    {
        $user = User::factory()->create(['credits' => 10]);

        $this->actingAsUser($user)
            ->postJson('/api/v1/jobs', $this->validPayload('virtual-mannequin'))
            ->assertStatus(201);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'credits' => 6]);

        $this->actingAsUser($user)
            ->postJson('/api/v1/jobs', $this->validPayload('product-staging'))
            ->assertStatus(201);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'credits' => 3]);

        $this->actingAsUser($user)
            ->postJson('/api/v1/jobs', $this->validPayload('promotional-banner'))
            ->assertStatus(201);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'credits' => 1]);
    }

    // JOB-04
    public function test_list_jobs_returns_paginated_results(): void
    {
        $user = User::factory()->create(['credits' => 20]);

        $this->actingAsUser($user)->postJson('/api/v1/jobs', $this->validPayload('virtual-mannequin'));
        $this->actingAsUser($user)->postJson('/api/v1/jobs', $this->validPayload('product-staging'));
        $this->actingAsUser($user)->postJson('/api/v1/jobs', $this->validPayload('promotional-banner'));

        $response = $this->actingAsUser($user)->getJson('/api/v1/jobs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'service_type', 'status', 'credits_used', 'created_at', 'completed_at']],
                'meta' => [],
            ])
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.current_page', 1);

        $meta = $response->json('meta');
        $this->assertSame(['current_page', 'per_page', 'total', 'last_page'], array_keys($meta));
    }

    public function test_list_jobs_filters_by_service_type(): void
    {
        $user = User::factory()->create(['credits' => 20]);

        AiJob::create([
            'user_id'         => $user->id,
            'service_type'    => 'virtual-mannequin',
            'input_image_url' => 'https://example.com/img.jpg',
            'prompt_payload'  => ['model_style' => 'test'],
            'status'          => 'processing',
            'output_urls'     => [],
            'credits_used'    => 4,
        ]);
        AiJob::create([
            'user_id'         => $user->id,
            'service_type'    => 'virtual-mannequin',
            'input_image_url' => 'https://example.com/img2.jpg',
            'prompt_payload'  => ['model_style' => 'test'],
            'status'          => 'processing',
            'output_urls'     => [],
            'credits_used'    => 4,
        ]);
        AiJob::create([
            'user_id'         => $user->id,
            'service_type'    => 'product-staging',
            'input_image_url' => 'https://example.com/img3.jpg',
            'prompt_payload'  => ['model_style' => 'test'],
            'status'          => 'processing',
            'output_urls'     => [],
            'credits_used'    => 3,
        ]);

        $response = $this->actingAsUser($user)->getJson('/api/v1/jobs?service_type=virtual-mannequin');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertSame('virtual-mannequin', $item['service_type']);
        }
    }

    public function test_list_jobs_filters_by_status(): void
    {
        $user = User::factory()->create(['credits' => 10]);

        $this->actingAsUser($user)->postJson('/api/v1/jobs', $this->validPayload('promotional-banner'));

        AiJob::create([
            'user_id'         => $user->id,
            'service_type'    => 'product-staging',
            'input_image_url' => 'https://example.com/img.jpg',
            'prompt_payload'  => ['model_style' => 'test'],
            'status'          => 'complete',
            'output_urls'     => [],
            'credits_used'    => 3,
        ]);

        $response = $this->actingAsUser($user)->getJson('/api/v1/jobs?status=processing');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        $data = $response->json('data');
        $this->assertSame('processing', $data[0]['status']);
    }

    // JOB-05
    public function test_show_job_returns_job_for_owner(): void
    {
        $user = User::factory()->create(['credits' => 10]);

        $createResponse = $this->actingAsUser($user)
            ->postJson('/api/v1/jobs', $this->validPayload('virtual-mannequin'));
        $createResponse->assertStatus(201);
        $jobId = $createResponse->json('id');

        $response = $this->actingAsUser($user)->getJson("/api/v1/jobs/{$jobId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id', 'user_id', 'service_type', 'input_image_url',
                'prompt_payload', 'status', 'output_urls', 'credits_used',
                'created_at', 'completed_at',
            ])
            ->assertJsonPath('id', $jobId);
    }

    public function test_show_job_returns_403_for_other_users_job(): void
    {
        $userA = User::factory()->create(['credits' => 10]);
        $userB = User::factory()->create(['credits' => 10]);

        $job = AiJob::create([
            'user_id'         => $userA->id,
            'service_type'    => 'virtual-mannequin',
            'input_image_url' => 'https://example.com/img.jpg',
            'prompt_payload'  => ['model_style' => 'test'],
            'status'          => 'processing',
            'output_urls'     => [],
            'credits_used'    => 4,
        ]);

        $response = $this->actingAsUser($userB)->getJson("/api/v1/jobs/{$job->id}");

        $response->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    public function test_show_job_returns_404_for_nonexistent_job(): void
    {
        $user = User::factory()->create(['credits' => 10]);

        $response = $this->actingAsUser($user)->getJson('/api/v1/jobs/99999');

        $response->assertStatus(404);
    }

    // JOB-06
    public function test_stats_returns_correct_counts(): void
    {
        $user = User::factory()->create();

        // 2 processing jobs, credits 4 each
        AiJob::create(['user_id' => $user->id, 'service_type' => 'virtual-mannequin', 'input_image_url' => 'https://example.com/img.jpg', 'prompt_payload' => ['k' => 'v'], 'status' => 'processing', 'output_urls' => [], 'credits_used' => 4]);
        AiJob::create(['user_id' => $user->id, 'service_type' => 'product-staging', 'input_image_url' => 'https://example.com/img.jpg', 'prompt_payload' => ['k' => 'v'], 'status' => 'processing', 'output_urls' => [], 'credits_used' => 4]);

        // 1 complete job, credits 3
        AiJob::create(['user_id' => $user->id, 'service_type' => 'product-staging', 'input_image_url' => 'https://example.com/img.jpg', 'prompt_payload' => ['k' => 'v'], 'status' => 'complete', 'output_urls' => ['https://example.com/out.jpg'], 'credits_used' => 3]);

        // 1 failed job, credits 2
        AiJob::create(['user_id' => $user->id, 'service_type' => 'promotional-banner', 'input_image_url' => 'https://example.com/img.jpg', 'prompt_payload' => ['k' => 'v'], 'status' => 'failed', 'output_urls' => [], 'credits_used' => 2]);

        $response = $this->actingAsUser($user)->getJson('/api/v1/jobs/stats');

        $response->assertStatus(200)
                 ->assertJson([
                     'total_jobs'         => 4,
                     'completed_jobs'     => 1,
                     'processing_jobs'    => 2,
                     'failed_jobs'        => 1,
                     'total_credits_used' => 13,
                 ]);

        $this->assertIsInt($response->json('total_jobs'));
        $this->assertIsInt($response->json('total_credits_used'));
    }

    public function test_stats_returns_zeros_for_new_user(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAsUser($user)->getJson('/api/v1/jobs/stats');
        $response->assertStatus(200)
                 ->assertJson([
                     'total_jobs'         => 0,
                     'completed_jobs'     => 0,
                     'processing_jobs'    => 0,
                     'failed_jobs'        => 0,
                     'total_credits_used' => 0,
                 ]);
        $this->assertIsInt($response->json('total_jobs'));
        $this->assertNull($response->json('nonexistent_key'));
    }

    // Auth guard
    public function test_all_job_endpoints_require_auth(): void
    {
        $this->postJson('/api/v1/jobs', $this->validPayload())->assertStatus(401);
        $this->getJson('/api/v1/jobs')->assertStatus(401);
        $this->getJson('/api/v1/jobs/1')->assertStatus(401);
        $this->getJson('/api/v1/jobs/stats')->assertStatus(401);
    }
}
