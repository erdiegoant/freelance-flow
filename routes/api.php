<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TimeLogController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

// Go worker callback — authenticated via X-Callback-Secret, not a user token
Route::post('/invoices/{invoice}/callback', [InvoiceController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::post('/clients', [ClientController::class, 'store']);

    Route::prefix('/clients/{client}')->group(function (): void {
        Route::get('/projects', [ProjectController::class, 'index']);
        Route::post('/projects', [ProjectController::class, 'store']);
    });

    Route::prefix('/projects/{project}')->group(function (): void {
        Route::post('/time-logs', [TimeLogController::class, 'store']);
        Route::post('/invoices', [InvoiceController::class, 'store']);
    });

    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
});
