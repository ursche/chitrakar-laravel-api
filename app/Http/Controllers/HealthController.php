<?php

namespace App\Http\Controllers;

class HealthController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}
