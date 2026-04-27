<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EsewaController extends Controller
{
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'package_id' => 'required|string',
        ]);

        $package = Package::findOrFail($request->input('package_id'));

        $tx = Transaction::create([
            'user_id'         => $request->user()->id,
            'package_id'      => $package->id,
            'amount_npr'      => $package->price_npr,
            'payment_gateway' => 'esewa',
            'status'          => 'pending',
            'credits_awarded' => $package->credits,
        ]);

        $uuid         = 'chitrakar-tx-' . $tx->id . '-' . time();
        $amount       = (string) $package->price_npr;
        $signedFields = "total_amount={$amount},transaction_uuid={$uuid},product_code=EPAYTEST";
        $signature    = base64_encode(hash_hmac('sha256', $signedFields, 'stub-secret', true));

        $tx->update(['transaction_uuid' => $uuid]);

        return response()->json([
            'transaction_id' => $tx->id,
            'esewa_form_url' => 'https://rc-epay.esewa.com.np/api/epay/main/v2/form',
            'form_fields'    => [
                'amount'                  => $amount,
                'tax_amount'              => '0',
                'total_amount'            => $amount,
                'transaction_uuid'        => $uuid,
                'product_code'            => 'EPAYTEST',
                'product_service_charge'  => '0',
                'product_delivery_charge' => '0',
                'success_url'             => config('app.url') . '/payment/esewa/success',
                'failure_url'             => config('app.url') . '/payment/esewa/failure',
                'signed_field_names'      => 'total_amount,transaction_uuid,product_code',
                'signature'               => $signature,
            ],
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $raw = base64_decode((string) $request->input('data', ''), true);
        if ($raw === false) {
            return response()->json(['message' => 'Invalid payment data.'], 400);
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || empty($decoded['transaction_uuid'])) {
            return response()->json(['message' => 'Invalid payment data.'], 400);
        }

        $tx = Transaction::where('transaction_uuid', $decoded['transaction_uuid'])
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->first();

        if (! $tx) {
            return response()->json(['message' => 'Invalid payment data.'], 400);
        }

        DB::transaction(function () use ($request, $tx, $decoded) {
            $request->user()->increment('credits', $tx->credits_awarded);
            $tx->update([
                'status'        => 'complete',
                'gateway_tx_id' => $decoded['transaction_code'] ?? null,
            ]);
        });

        $request->user()->refresh();
        $tx->refresh();

        return response()->json([
            'success'            => true,
            'credits_awarded'    => $tx->credits_awarded,
            'new_credit_balance' => $request->user()->credits,
            'transaction'        => [
                'id'              => $tx->id,
                'amount_npr'      => $tx->amount_npr,
                'payment_gateway' => $tx->payment_gateway,
                'status'          => 'complete',
                'credits_awarded' => $tx->credits_awarded,
                'gateway_tx_id'   => $tx->gateway_tx_id,
                'created_at'      => $tx->created_at->toIso8601String(),
            ],
        ]);
    }
}
