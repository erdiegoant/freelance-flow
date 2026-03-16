<?php

use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TimeLogController;
use Illuminate\Support\Facades\Route;

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
Route::post('/invoices/{invoice}/callback', [InvoiceController::class, 'callback']);
