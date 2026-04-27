<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\EsewaController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\KhaltiController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Health
Route::get('/health', [HealthController::class, 'index']);

// Public auth
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// Public data
Route::get('/packages', [PackageController::class, 'index']);
Route::get('/config/options', [ConfigController::class, 'options']);

// Webhook (secret header, not Sanctum)
Route::post('/webhooks/job-complete', [WebhookController::class, 'jobComplete']);

// Authenticated (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/user/me', [UserController::class, 'me']);
    Route::put('/user/me', [UserController::class, 'update']);

    Route::post('/upload', [UploadController::class, 'store']);

    Route::post('/payments/khalti/initiate', [KhaltiController::class, 'initiate']);
    Route::post('/payments/khalti/verify', [KhaltiController::class, 'verify']);
    Route::post('/payments/esewa/initiate', [EsewaController::class, 'initiate']);
    Route::post('/payments/esewa/verify', [EsewaController::class, 'verify']);

    Route::get('/transactions', [TransactionController::class, 'index']);

    // CRITICAL: /jobs/stats MUST be registered BEFORE /jobs/{job} — RESEARCH Pitfall 4
    Route::get('/jobs/stats', [JobController::class, 'stats']);
    Route::get('/jobs', [JobController::class, 'index']);
    Route::post('/jobs', [JobController::class, 'store']);
    Route::get('/jobs/{job}', [JobController::class, 'show']);
});
