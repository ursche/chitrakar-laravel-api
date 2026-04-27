<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $paginator = Transaction::where('user_id', $request->user()->id)
            ->latest()
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = collect($paginator->items())->map(function (Transaction $tx) {
            return [
                'id'              => $tx->id,
                'amount_npr'      => $tx->amount_npr,
                'payment_gateway' => $tx->payment_gateway,
                'status'          => $tx->status,
                'credits_awarded' => $tx->credits_awarded,
                'gateway_tx_id'   => $tx->gateway_tx_id,
                'created_at'      => $tx->created_at->toIso8601String(),
            ];
        })->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }
}
