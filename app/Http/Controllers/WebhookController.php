<?php

namespace App\Http\Controllers;

class WebhookController extends Controller
{
    public function jobComplete(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['message' => 'stub: WebhookController::jobComplete']);
    }
}
