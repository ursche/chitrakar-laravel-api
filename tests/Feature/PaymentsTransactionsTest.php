<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentsTransactionsTest extends TestCase
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
    // KHL-01: POST /api/v1/payments/khalti/initiate
    // -------------------------------------------------------------------------

    public function test_khalti_initiate_returns_pidx_and_payment_url(): void
    {
        $user = User::factory()->create(['credits' => 0]);

        $response = $this->actingAsUser($user)->postJson('/api/v1/payments/khalti/initiate', [
            'package_id' => 'growth',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['pidx', 'payment_url', 'transaction_id', 'expires_at']);

        $pidx = $response->json('pidx');
        $this->assertStringStartsWith('KHL-', $pidx);
        $this->assertSame(20, strlen($pidx));               // 'KHL-' + 16 random chars
        $this->assertSame('https://pay.khalti.com/?pidx=' . $pidx, $response->json('payment_url'));

        $this->assertDatabaseHas('transactions', [
            'user_id'         => $user->id,
            'package_id'      => 'growth',
            'amount_npr'      => 2499,
            'payment_gateway' => 'khalti',
            'status'          => 'pending',
            'credits_awarded' => 30,
            'gateway_tx_id'   => $pidx,
        ]);
    }

    public function test_khalti_initiate_returns_404_for_unknown_package(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsUser($user)->postJson('/api/v1/payments/khalti/initiate', [
            'package_id' => 'does-not-exist',
        ]);

        $response->assertStatus(404)
                 ->assertJsonStructure(['message']);
    }

    public function test_khalti_initiate_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/payments/khalti/initiate', [
            'package_id' => 'growth',
        ]);

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // KHL-02: POST /api/v1/payments/khalti/verify
    // -------------------------------------------------------------------------

    public function test_khalti_verify_awards_credits(): void
    {
        $user = User::factory()->create(['credits' => 5]);

        $initiate = $this->actingAsUser($user)->postJson('/api/v1/payments/khalti/initiate', [
            'package_id' => 'growth',
        ]);
        $pidx = $initiate->json('pidx');

        $response = $this->actingAsUser($user)->postJson('/api/v1/payments/khalti/verify', [
            'pidx' => $pidx,
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'credits_awarded',
                     'new_credit_balance',
                     'transaction' => [
                         'id', 'amount_npr', 'payment_gateway', 'status',
                         'credits_awarded', 'gateway_tx_id', 'created_at',
                     ],
                 ])
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('credits_awarded', 30)
                 ->assertJsonPath('new_credit_balance', 35)
                 ->assertJsonPath('transaction.payment_gateway', 'khalti')
                 ->assertJsonPath('transaction.status', 'complete')
                 ->assertJsonPath('transaction.gateway_tx_id', $pidx);

        $this->assertDatabaseHas('users', [
            'id'      => $user->id,
            'credits' => 35,
        ]);
        $this->assertDatabaseHas('transactions', [
            'gateway_tx_id' => $pidx,
            'status'        => 'complete',
        ]);
    }

    public function test_khalti_verify_returns_400_for_invalid_pidx(): void
    {
        $user = User::factory()->create(['credits' => 0]);

        // Unknown pidx
        $response = $this->actingAsUser($user)->postJson('/api/v1/payments/khalti/verify', [
            'pidx' => 'KHL-doesnotexist1234',
        ]);
        $response->assertStatus(400)
                 ->assertJsonPath('message', 'Payment not found or already verified.');

        // Already-verified pidx (replay defense)
        $initiate = $this->actingAsUser($user)->postJson('/api/v1/payments/khalti/initiate', [
            'package_id' => 'starter',
        ]);
        $pidx = $initiate->json('pidx');

        $first = $this->actingAsUser($user)->postJson('/api/v1/payments/khalti/verify', [
            'pidx' => $pidx,
        ]);
        $first->assertStatus(200);

        $second = $this->actingAsUser($user)->postJson('/api/v1/payments/khalti/verify', [
            'pidx' => $pidx,
        ]);
        $second->assertStatus(400)
               ->assertJsonPath('message', 'Payment not found or already verified.');

        // Confirm credits were only awarded once
        $this->assertDatabaseHas('users', [
            'id'      => $user->id,
            'credits' => 10,    // starter pack credits
        ]);
    }

    public function test_khalti_verify_cannot_use_another_users_pidx(): void
    {
        $userA = User::factory()->create(['credits' => 5]);
        $userB = User::factory()->create(['credits' => 5]);

        // User B initiates a payment
        $initiate = $this->actingAsUser($userB)->postJson('/api/v1/payments/khalti/initiate', [
            'package_id' => 'pro',
        ]);
        $pidxB = $initiate->json('pidx');

        // User A tries to verify it
        $response = $this->actingAsUser($userA)->postJson('/api/v1/payments/khalti/verify', [
            'pidx' => $pidxB,
        ]);

        $response->assertStatus(400)
                 ->assertJsonPath('message', 'Payment not found or already verified.');

        // Neither user's credits changed, B's tx remains pending
        $this->assertDatabaseHas('users', ['id' => $userA->id, 'credits' => 5]);
        $this->assertDatabaseHas('users', ['id' => $userB->id, 'credits' => 5]);
        $this->assertDatabaseHas('transactions', [
            'gateway_tx_id' => $pidxB,
            'status'        => 'pending',
        ]);
    }

    // -------------------------------------------------------------------------
    // ESW-01: POST /api/v1/payments/esewa/initiate
    // -------------------------------------------------------------------------

    public function test_esewa_initiate_returns_form_fields(): void
    {
        $user = User::factory()->create(['credits' => 0]);

        $response = $this->actingAsUser($user)->postJson('/api/v1/payments/esewa/initiate', [
            'package_id' => 'starter',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'transaction_id',
                     'esewa_form_url',
                     'form_fields' => [
                         'amount', 'tax_amount', 'total_amount', 'transaction_uuid',
                         'product_code', 'product_service_charge', 'product_delivery_charge',
                         'success_url', 'failure_url', 'signed_field_names', 'signature',
                     ],
                 ])
                 ->assertJsonPath('esewa_form_url', 'https://rc-epay.esewa.com.np/api/epay/main/v2/form')
                 ->assertJsonPath('form_fields.amount', '999')
                 ->assertJsonPath('form_fields.total_amount', '999')
                 ->assertJsonPath('form_fields.product_code', 'EPAYTEST')
                 ->assertJsonPath('form_fields.tax_amount', '0')
                 ->assertJsonPath('form_fields.product_service_charge', '0')
                 ->assertJsonPath('form_fields.product_delivery_charge', '0')
                 ->assertJsonPath('form_fields.signed_field_names', 'total_amount,transaction_uuid,product_code');

        // Recompute the stub HMAC and assert it matches byte-for-byte
        $uuid          = $response->json('form_fields.transaction_uuid');
        $signedFields  = "total_amount=999,transaction_uuid={$uuid},product_code=EPAYTEST";
        $expectedSig   = base64_encode(hash_hmac('sha256', $signedFields, 'stub-secret', true));
        $this->assertSame($expectedSig, $response->json('form_fields.signature'));

        // transaction_uuid format
        $this->assertMatchesRegularExpression('/^chitrakar-tx-\d+-\d+$/', $uuid);

        // DB row state — gateway_tx_id stays NULL on initiate
        $this->assertDatabaseHas('transactions', [
            'user_id'          => $user->id,
            'package_id'       => 'starter',
            'amount_npr'       => 999,
            'payment_gateway'  => 'esewa',
            'status'           => 'pending',
            'credits_awarded'  => 10,
            'transaction_uuid' => $uuid,
            'gateway_tx_id'    => null,
        ]);
    }

    public function test_esewa_initiate_returns_404_for_unknown_package(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsUser($user)->postJson('/api/v1/payments/esewa/initiate', [
            'package_id' => 'does-not-exist',
        ]);

        $response->assertStatus(404)
                 ->assertJsonStructure(['message']);
    }

    // -------------------------------------------------------------------------
    // ESW-02: POST /api/v1/payments/esewa/verify
    // -------------------------------------------------------------------------

    public function test_esewa_verify_awards_credits(): void
    {
        $user = User::factory()->create(['credits' => 5]);

        $initiate = $this->actingAsUser($user)->postJson('/api/v1/payments/esewa/initiate', [
            'package_id' => 'starter',
        ]);
        $uuid = $initiate->json('form_fields.transaction_uuid');

        $payload = [
            'transaction_code'    => '000AAA',
            'status'              => 'COMPLETE',
            'total_amount'        => '999',
            'transaction_uuid'    => $uuid,
            'product_code'        => 'EPAYTEST',
            'signed_field_names'  => 'transaction_code,status,total_amount,transaction_uuid,product_code',
            'signature'           => 'stub-signature',
        ];
        $data = base64_encode(json_encode($payload));

        $response = $this->actingAsUser($user)->postJson('/api/v1/payments/esewa/verify', [
            'data' => $data,
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'credits_awarded',
                     'new_credit_balance',
                     'transaction' => [
                         'id', 'amount_npr', 'payment_gateway', 'status',
                         'credits_awarded', 'gateway_tx_id', 'created_at',
                     ],
                 ])
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('credits_awarded', 10)
                 ->assertJsonPath('new_credit_balance', 15)
                 ->assertJsonPath('transaction.payment_gateway', 'esewa')
                 ->assertJsonPath('transaction.status', 'complete')
                 ->assertJsonPath('transaction.gateway_tx_id', '000AAA');

        $this->assertDatabaseHas('users', [
            'id'      => $user->id,
            'credits' => 15,
        ]);
        $this->assertDatabaseHas('transactions', [
            'transaction_uuid' => $uuid,
            'status'           => 'complete',
            'gateway_tx_id'    => '000AAA',
        ]);

        // Replay defense — second verify with same uuid returns 400
        $second = $this->actingAsUser($user)->postJson('/api/v1/payments/esewa/verify', [
            'data' => $data,
        ]);
        $second->assertStatus(400)
               ->assertJsonPath('message', 'Invalid payment data.');

        // Cross-user defense — different user cannot verify this uuid
        $otherUser = User::factory()->create(['credits' => 0]);

        $payloadOther = $payload;
        $dataOther    = base64_encode(json_encode($payloadOther));

        $cross = $this->actingAsUser($otherUser)->postJson('/api/v1/payments/esewa/verify', [
            'data' => $dataOther,
        ]);
        $cross->assertStatus(400)
              ->assertJsonPath('message', 'Invalid payment data.');

        $this->assertDatabaseHas('users', ['id' => $otherUser->id, 'credits' => 0]);
    }

    public function test_esewa_verify_returns_400_for_invalid_data(): void
    {
        $user = User::factory()->create(['credits' => 0]);

        // Invalid base64 (strict mode rejects non-base64 chars)
        $resp1 = $this->actingAsUser($user)->postJson('/api/v1/payments/esewa/verify', [
            'data' => '!!!not-base64!!!',
        ]);
        $resp1->assertStatus(400)
              ->assertJsonPath('message', 'Invalid payment data.');

        // Valid base64 of non-JSON content
        $resp2 = $this->actingAsUser($user)->postJson('/api/v1/payments/esewa/verify', [
            'data' => base64_encode('this is not json'),
        ]);
        $resp2->assertStatus(400)
              ->assertJsonPath('message', 'Invalid payment data.');

        // Valid base64+JSON but missing the transaction_uuid key
        $resp3 = $this->actingAsUser($user)->postJson('/api/v1/payments/esewa/verify', [
            'data' => base64_encode(json_encode(['transaction_code' => 'XYZ'])),
        ]);
        $resp3->assertStatus(400)
              ->assertJsonPath('message', 'Invalid payment data.');

        // Empty string
        $resp4 = $this->actingAsUser($user)->postJson('/api/v1/payments/esewa/verify', [
            'data' => '',
        ]);
        $resp4->assertStatus(400)
              ->assertJsonPath('message', 'Invalid payment data.');

        // Confirm no credits were awarded across any of the bad calls
        $this->assertDatabaseHas('users', ['id' => $user->id, 'credits' => 0]);
    }

    // -------------------------------------------------------------------------
    // TXN-01: GET /api/v1/transactions
    // -------------------------------------------------------------------------

    public function test_transactions_returns_paginated_history(): void
    {
        $user = User::factory()->create(['credits' => 0]);

        // Insert 3 transactions for this user, oldest first
        $t1 = Transaction::create([
            'user_id'         => $user->id,
            'package_id'      => 'starter',
            'amount_npr'      => 999,
            'payment_gateway' => 'esewa',
            'status'          => 'complete',
            'credits_awarded' => 10,
            'gateway_tx_id'   => 'ESW-001',
            'transaction_uuid'=> 'uuid-1',
        ]);
        $t2 = Transaction::create([
            'user_id'         => $user->id,
            'package_id'      => 'growth',
            'amount_npr'      => 2499,
            'payment_gateway' => 'khalti',
            'status'          => 'complete',
            'credits_awarded' => 30,
            'gateway_tx_id'   => 'KHL-002',
        ]);
        $t3 = Transaction::create([
            'user_id'         => $user->id,
            'package_id'      => 'pro',
            'amount_npr'      => 4999,
            'payment_gateway' => 'khalti',
            'status'          => 'pending',
            'credits_awarded' => 75,
            'gateway_tx_id'   => 'KHL-003',
        ]);

        $response = $this->actingAsUser($user)->getJson('/api/v1/transactions');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         ['id', 'amount_npr', 'payment_gateway', 'status', 'credits_awarded', 'gateway_tx_id', 'created_at'],
                     ],
                     'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                 ]);

        $this->assertCount(3, $response->json('data'));

        // Newest first (latest()) — t3, t2, t1
        $ids = array_map(fn ($row) => $row['id'], $response->json('data'));
        $this->assertSame([$t3->id, $t2->id, $t1->id], $ids);

        // Data items must NOT include package_id, user_id, transaction_uuid, updated_at
        foreach ($response->json('data') as $row) {
            $this->assertArrayNotHasKey('package_id', $row);
            $this->assertArrayNotHasKey('user_id', $row);
            $this->assertArrayNotHasKey('transaction_uuid', $row);
            $this->assertArrayNotHasKey('updated_at', $row);
        }
    }

    public function test_transactions_only_returns_own_records(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Two transactions for User A
        $a1 = Transaction::create([
            'user_id'         => $userA->id, 'package_id' => 'starter',
            'amount_npr'      => 999, 'payment_gateway' => 'esewa', 'status' => 'complete',
            'credits_awarded' => 10, 'gateway_tx_id' => 'ESW-A1',
        ]);
        $a2 = Transaction::create([
            'user_id'         => $userA->id, 'package_id' => 'growth',
            'amount_npr'      => 2499, 'payment_gateway' => 'khalti', 'status' => 'complete',
            'credits_awarded' => 30, 'gateway_tx_id' => 'KHL-A2',
        ]);

        // One transaction for User B
        Transaction::create([
            'user_id'         => $userB->id, 'package_id' => 'pro',
            'amount_npr'      => 4999, 'payment_gateway' => 'khalti', 'status' => 'complete',
            'credits_awarded' => 75, 'gateway_tx_id' => 'KHL-B1',
        ]);

        $response = $this->actingAsUser($userA)->getJson('/api/v1/transactions');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));

        $ids = array_map(fn ($row) => $row['id'], $response->json('data'));
        sort($ids);
        $expected = [$a1->id, $a2->id];
        sort($expected);
        $this->assertSame($expected, $ids);

        // Confirm B's gateway_tx_id is not present in A's response
        $gatewayIds = array_map(fn ($row) => $row['gateway_tx_id'], $response->json('data'));
        $this->assertNotContains('KHL-B1', $gatewayIds);
    }

    public function test_transactions_returns_correct_meta(): void
    {
        $user = User::factory()->create();

        // Create 7 transactions
        for ($i = 1; $i <= 7; $i++) {
            Transaction::create([
                'user_id'         => $user->id,
                'package_id'      => 'starter',
                'amount_npr'      => 999,
                'payment_gateway' => 'esewa',
                'status'          => 'complete',
                'credits_awarded' => 10,
                'gateway_tx_id'   => 'ESW-' . $i,
            ]);
        }

        // per_page=5, page=2 → 2 records on page 2 (last_page=2)
        $response = $this->actingAsUser($user)->getJson('/api/v1/transactions?per_page=5&page=2');

        $response->assertStatus(200)
                 ->assertJsonPath('meta.current_page', 2)
                 ->assertJsonPath('meta.per_page', 5)
                 ->assertJsonPath('meta.total', 7)
                 ->assertJsonPath('meta.last_page', 2);
        $this->assertCount(2, $response->json('data'));

        // Meta MUST contain ONLY 4 keys (no Laravel-internal links/path/from/to/etc.)
        $meta = $response->json('meta');
        $this->assertSame(['current_page', 'per_page', 'total', 'last_page'], array_keys($meta));

        // per_page upper bound — request 99999, server caps at 100
        $bigResp = $this->actingAsUser($user)->getJson('/api/v1/transactions?per_page=99999');
        $bigResp->assertStatus(200)
                ->assertJsonPath('meta.per_page', 100);
    }

    public function test_transactions_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/transactions');

        $response->assertStatus(401);
    }
}
