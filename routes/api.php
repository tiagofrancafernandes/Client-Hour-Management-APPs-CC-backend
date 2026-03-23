<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientUserController;
use App\Http\Controllers\Api\CreditPurchaseController;
use App\Http\Controllers\Api\ImportPlanController;
use App\Http\Controllers\Api\LedgerEntryController;
use App\Http\Controllers\Api\PaymentApprovalController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentReceiptController;
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

    // Client Users Routes
    Route::prefix('clients/{client}')->group(function () {
        Route::get('/users', [ClientUserController::class, 'index']);
        Route::post('/users', [ClientUserController::class, 'store']);
        Route::put('/users/{user}', [ClientUserController::class, 'update']);
        Route::delete('/users/{user}', [ClientUserController::class, 'destroy']);
    });

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

    Route::prefix('import-plans')->group(function () {
        Route::get('/template/download', [ImportPlanController::class, 'downloadTemplate']);
        Route::get('/', [ImportPlanController::class, 'index']);
        Route::post('/', [ImportPlanController::class, 'store']);
        Route::get('/{importPlan}', [ImportPlanController::class, 'show']);

        Route::post('/{importPlan}/confirm', [ImportPlanController::class, 'confirm']);
        Route::post('/{importPlan}/cancel', [ImportPlanController::class, 'cancel']);

        Route::post('/{importPlan}/rows', [ImportPlanController::class, 'addRow']);
        Route::put('/rows/{importPlanRow}', [ImportPlanController::class, 'updateRow']);
        Route::delete('/rows/{importPlanRow}', [ImportPlanController::class, 'deleteRow']);
    });

    Route::prefix('reports')->group(function () {
        Route::get('/', [ReportController::class, 'index']);
        Route::get('/summary', [ReportController::class, 'summary']);
        Route::get('/by-wallet', [ReportController::class, 'byWallet']);
        Route::get('/by-client', [ReportController::class, 'byClient']);
        Route::get('/export', [ReportController::class, 'export']);
    });

    // Credit Purchase Routes
    Route::prefix('credit-purchases')->group(function () {
        Route::get('/', [CreditPurchaseController::class, 'index']);
        Route::post('/', [CreditPurchaseController::class, 'store']);
        Route::get('/{creditPurchase}', [CreditPurchaseController::class, 'show']);

        // Payment routes
        Route::post('/{creditPurchase}/payments', [PaymentController::class, 'store']);
        Route::post('/{creditPurchase}/payments/{creditPurchasePayment}/upload-receipt', [PaymentReceiptController::class, 'store']);
        Route::get('/payments/{creditPurchasePayment}/receipt-url', [PaymentReceiptController::class, 'getUrl']);
    });

    // Payment Approval Routes (Admin only)
    Route::prefix('payments')->group(function () {
        Route::get('/pending', [PaymentApprovalController::class, 'pending']);
        Route::post('/{creditPurchasePayment}/approve', [PaymentApprovalController::class, 'approve']);
        Route::post('/{creditPurchasePayment}/reject', [PaymentApprovalController::class, 'reject']);
    });
});
