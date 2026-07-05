<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DebugConfigController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientUserController;
use App\Http\Controllers\Api\CreditPurchaseController;
use App\Http\Controllers\Api\ImportPlanController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LedgerEntryController;
use App\Http\Controllers\Api\PaymentApprovalController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentReceiptController;
use App\Http\Controllers\Api\ProductServiceController;
use App\Http\Controllers\Api\PublicResourceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TimerController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\Admin\PaymentMethodConfigController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (Request $request) => DebugConfigController::rootPathInfo($request, __FILE__ . ':' . __LINE__));

// Health Check Routes (no authentication required)
Route::prefix('health-check')->group(function () {
    Route::get('/basic', [HealthCheckController::class, 'basic']);
    Route::get('/database', [HealthCheckController::class, 'database']);
    Route::get('/storage', [HealthCheckController::class, 'storage']);
    Route::get('/all', [HealthCheckController::class, 'all']);
    Route::post('/all', [HealthCheckController::class, 'all']);
});

/* Debug Config Routes (dev authentication required) */
Route::prefix('debug')->group(function () {
    Route::get('/config', DebugConfigController::class);
    Route::post('/config', DebugConfigController::class);
});

Route::prefix('public')->name('api.public.')->group(function () {
    Route::any('/timezones', [PublicResourceController::class, 'timezones'])->name('timezones');
});

Route::prefix('auth')->group(function () {
    // Public routes (no authentication required)
    Route::get('/resources', [AuthController::class, 'getAuthResources']);
    Route::post('/login', [AuthController::class, 'login']);

    // Registration routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/register/verify', [AuthController::class, 'registerVerifyEmail']);
    Route::post('/register/complete', [AuthController::class, 'registerComplete']);

    // Password Recovery routes
    Route::post('/password-recovery/request', [AuthController::class, 'requestPasswordRecovery']);
    Route::post('/password-recovery/verify', [AuthController::class, 'verifyPasswordRecoveryToken']);
    Route::post('/password-recovery/reset', [AuthController::class, 'resetPassword']);

    // Authenticated routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/validate', [AuthController::class, 'validateToken']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    // User Management
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/options', [UserController::class, 'availableOptions']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::put('/{user}/role', [UserController::class, 'updateRole']);
        Route::put('/{user}/permissions', [UserController::class, 'updatePermissions']);
        Route::put('/{user}/password', [UserController::class, 'updatePassword']);
    });

    Route::apiResource('clients', ClientController::class);

    // Client Users Routes
    Route::prefix('clients/{client}')->group(function () {
        Route::get('/users', [ClientUserController::class, 'index']);
        Route::post('/users', [ClientUserController::class, 'store']);
        Route::post('/users/attach', [ClientUserController::class, 'attach']);
        Route::put('/users/{user}', [ClientUserController::class, 'update']);
        Route::put('/users/{user}/set-admin', [ClientUserController::class, 'setAdmin']);
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

    // Invoice Routes
    Route::apiResource('invoices', InvoiceController::class);
    Route::get('/invoices/{invoice}/download-markdown', [InvoiceController::class, 'downloadMarkdown']);

    // Products and Services Routes
    Route::apiResource('products-services', ProductServiceController::class);

    // Credit Purchase Routes
    Route::prefix('credit-purchases')->group(function () {
        Route::get('/', [CreditPurchaseController::class, 'index']);
        Route::post('/', [CreditPurchaseController::class, 'store']);
        Route::get('/{creditPurchase}', [CreditPurchaseController::class, 'show']);

        // Payment routes
        Route::post('/{creditPurchase}/payments', [PaymentController::class, 'store']);
        Route::put('/{creditPurchase}/payments/{creditPurchasePayment}/set-method', [PaymentController::class, 'setMethod']);
        Route::post('/{creditPurchase}/payments/{creditPurchasePayment}/upload-receipt', [PaymentReceiptController::class, 'store']);
    });

    // Payment Routes (Admin approval + receipt URL)
    Route::prefix('payments')->group(function () {
        Route::get('/pending', [PaymentApprovalController::class, 'pending']);
        Route::post('/{creditPurchasePayment}/approve', [PaymentApprovalController::class, 'approve']);
        Route::post('/{creditPurchasePayment}/reject', [PaymentApprovalController::class, 'reject']);
        Route::get('/{creditPurchasePayment}/receipt-download', [PaymentReceiptController::class, 'download']);
    });

    // Payment Methods Routes
    Route::get('payment-methods', [PaymentController::class, 'getMethods']);

    // Payment Method Configuration (Admin)
    Route::prefix('admin')->group(function () {
        Route::get('payment-method-configs', [PaymentMethodConfigController::class, 'index']);
        Route::get('payment-method-configs/{paymentMethodConfig}', [PaymentMethodConfigController::class, 'show']);
        Route::put('payment-method-configs/{paymentMethodConfig}', [PaymentMethodConfigController::class, 'update']);
        Route::post('payment-method-configs/{paymentMethodConfig}/toggle', [PaymentMethodConfigController::class, 'toggle']);
    });
});
