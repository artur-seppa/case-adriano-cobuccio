<?php

use App\Http\Controllers\Api\V1\DepositController;
use App\Http\Controllers\Api\V1\ReversalController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\TransferController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/wallet', [WalletController::class, 'show']);
    Route::get('/wallet/statement', [WalletController::class, 'statement']);
    Route::get('/wallet/sessions', [SessionController::class, 'index']);
    Route::delete('/wallet/sessions/{id}', [SessionController::class, 'destroy']);

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);

    Route::middleware(['verified', 'idempotency', 'throttle:transfers'])->group(function () {
        Route::post('/deposits', DepositController::class);
        Route::post('/transfers', TransferController::class);
        Route::post('/transactions/{transaction}/reversal', ReversalController::class);
    });
});
