<?php

use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\LedgerEntryController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    Route::apiResource('clients', ClientController::class);

    Route::apiResource('wallets', WalletController::class);
    Route::get('/wallets/{wallet}/entries', [WalletController::class, 'entries']);
    Route::get('/wallets/{wallet}/balance', [WalletController::class, 'balance']);

    Route::apiResource('ledger-entries', LedgerEntryController::class)->only(['index', 'store', 'show']);

    Route::apiResource('tags', TagController::class);

    Route::prefix('reports')->group(function () {
        Route::get('/', [ReportController::class, 'index']);
        Route::get('/summary', [ReportController::class, 'summary']);
        Route::get('/by-wallet', [ReportController::class, 'byWallet']);
        Route::get('/by-client', [ReportController::class, 'byClient']);
    });
});
