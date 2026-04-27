<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KhaltiController extends Controller
{
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'package_id' => 'required|string',
        ]);

        $package = Package::findOrFail($request->input('package_id'));

        $pidx = 'KHL-' . Str::random(16);

        $tx = Transaction::create([
            'user_id'         => $request->user()->id,
            'package_id'      => $package->id,
            'amount_npr'      => $package->price_npr,
            'payment_gateway' => 'khalti',
            'status'          => 'pending',
            'credits_awarded' => $package->credits,
            'gateway_tx_id'   => $pidx,
        ]);

        return response()->json([
            'pidx'           => $pidx,
            'payment_url'    => 'https://pay.khalti.com/?pidx=' . $pidx,
            'transaction_id' => $tx->id,
            'expires_at'     => now()->addMinutes(30)->toIso8601String(),
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'pidx' => 'required|string',
        ]);

        $tx = Transaction::where('gateway_tx_id', $request->input('pidx'))
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->first();

        if (! $tx) {
            return response()->json(['message' => 'Payment not found or already verified.'], 400);
        }

        DB::transaction(function () use ($request, $tx) {
            $request->user()->increment('credits', $tx->credits_awarded);
            $tx->update(['status' => 'complete']);
        });

        $request->user()->refresh();

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
