<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\LedgerEntryController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TimerController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/validate', [AuthController::class, 'validateToken']);
    });
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    Route::apiResource('clients', ClientController::class);

    Route::apiResource('wallets', WalletController::class);
    Route::get('/wallets/{wallet}/entries', [WalletController::class, 'entries']);
    Route::get('/wallets/{wallet}/balance', [WalletController::class, 'balance']);

    Route::apiResource('ledger-entries', LedgerEntryController::class)->only(['index', 'store', 'show']);

    Route::apiResource('tags', TagController::class);

    Route::prefix('timers')->group(function () {
        Route::get('/', [TimerController::class, 'index']);
        Route::get('/active', [TimerController::class, 'active']);
        Route::post('/', [TimerController::class, 'store']);
        Route::get('/{timer}', [TimerController::class, 'show']);
        Route::put('/{timer}', [TimerController::class, 'update']);
        Route::delete('/{timer}', [TimerController::class, 'destroy']);

        Route::post('/{timer}/pause', [TimerController::class, 'pause']);
        Route::post('/{timer}/resume', [TimerController::class, 'resume']);
        Route::post('/{timer}/stop', [TimerController::class, 'stop']);
        Route::post('/{timer}/cancel', [TimerController::class, 'cancel']);
        Route::post('/{timer}/confirm', [TimerController::class, 'confirm']);
        Route::put('/{timer}/cycles', [TimerController::class, 'updateCycles']);
    });

    Route::prefix('reports')->group(function () {
        Route::get('/', [ReportController::class, 'index']);
        Route::get('/summary', [ReportController::class, 'summary']);
        Route::get('/by-wallet', [ReportController::class, 'byWallet']);
        Route::get('/by-client', [ReportController::class, 'byClient']);
        Route::get('/export', [ReportController::class, 'export']);
    });
});
