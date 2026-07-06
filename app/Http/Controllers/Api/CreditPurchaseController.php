<?php

namespace App\Http\Controllers\Api;

use App\Enums\CreditPurchaseStatus;
use Illuminate\Contracts\Database\Eloquent\Builder;
use App\Helpers\PaymentHelpers;
use App\Http\Controllers\Controller;
use App\Models\CreditPurchase;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditPurchaseController extends Controller
{
    /**
     * Store a credit purchase
     * POST /api/credit-purchases
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();

        $request->validate([
            'wallet_id' => ['required', 'integer', 'exists:wallets,id'],
            'total_hours' => ['required', 'numeric', 'gt:0'],
            'total_price' => ['required', 'numeric', 'gt:0'],
        ]);

        $wallet = Wallet::findOrFail($request->input('wallet_id'));

        // Verificar se wallet tem credit_purchase_allowed habilitado
        if (!$wallet->credit_purchase_allowed) {
            return response()->json([
                'message' => 'Credit purchases are not allowed for this wallet',
            ], 422);
        }

        // Verificar se usuario é customer deste wallet
        if ($user->hasRole('customer') && $user->customer_id !== $wallet->client_id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        // Determinar customer_id
        if ($user->hasRole('customer')) {
            $customerId = $user->id;
        } else {
            $customerId = $request->input('customer_id', $user->id);
        }

        // Criar purchase
        $purchase = CreditPurchase::create([
            'wallet_id' => $wallet->id,
            'customer_id' => $customerId,
            'total_hours' => $request->input('total_hours'),
            'total_price' => $request->input('total_price'),
            'currency_code' => $wallet->currency_code,
            'status' => CreditPurchaseStatus::PENDING,
        ]);

        return response()->json([
            'data' => $purchase->load(['wallet', 'customer']),
            'message' => 'Credit purchase created successfully',
        ], 201);
    }

    /**
     * Show a credit purchase
     * GET /api/credit-purchases/{id}
     */
    public function show(CreditPurchase $creditPurchase): JsonResponse
    {
        $user = auth()->user();

        if ($user->hasRole('customer') && $user->id !== $creditPurchase->customer_id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json(
            $creditPurchase->load(['wallet.client', 'customer', 'payments'])
        );
    }

    /**
     * List credit purchases
     * GET /api/credit-purchases
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = CreditPurchase::query();
        $pendingApprovals = $request->boolean('pending_approvals') || !$user->hasRole('customer');
        $canPurchaseApprove = $user->hasAnyPermission(['credit_purchase.approve']);

        if (!$canPurchaseApprove) {
            $pendingApprovals = false;
        }

        // Filtrar por wallet se fornecido
        if ($request->has('wallet_id')) {
            $query->where('wallet_id', $request->input('wallet_id'));
        }

        // Filtrar por client se fornecido
        if ($request->has('client_id')) {
            $query->whereHas('wallet', function ($walletQuery) use ($request) {
                $walletQuery->where('client_id', $request->input('client_id'));
            });
        }

        // Filtrar por payment method se fornecido
        if ($request->has('payment_method')) {
            $query->whereHas('payments', function ($paymentQuery) use ($request) {
                $paymentQuery->where('payment_method', $request->input('payment_method'));
            });
        }

        $offlinePaymentMethods = PaymentHelpers::getActiveOfflinePaymentMethods()->keys()->toArray();

        $query->when($pendingApprovals, function (Builder $q) use ($offlinePaymentMethods, $request) {
            $q->whereHas('payments', function ($paymentQuery) use ($offlinePaymentMethods) {
                $paymentQuery
                    ->where('payment_status', 'pending')
                    ->where(function ($conditionQuery) use ($offlinePaymentMethods) {
                        $conditionQuery
                            ->whereNotIn('payment_method', $offlinePaymentMethods)
                            ->orWhere(function ($offlineQuery) use ($offlinePaymentMethods) {
                                $offlineQuery
                                    ->whereIn('payment_method', $offlinePaymentMethods)
                                    ->whereNotNull('pix_receipt_path');
                            })
                            ;
                    });
            });
        });
        // ->orDoesntHave('payments');

        // Clientes veem apenas suas compras
        if ($user->hasRole('customer') || !$canPurchaseApprove) {
            $query->where('customer_id', $user->id);
        }

        $purchases = $query
            ->with(['wallet.client', 'customer', 'payments'])
            ->latest()
            ->paginate(15);

        return response()->json($purchases);
    }

    /**
     * Cancel a credit purchase (only if not approved).
     * DELETE /api/credit-purchases/{id}
     */
    public function destroy(CreditPurchase $creditPurchase): JsonResponse
    {
        $user = auth()->user();

        if ($user->hasRole('customer') && $user->id !== $creditPurchase->customer_id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($creditPurchase->status === CreditPurchaseStatus::APPROVED) {
            return response()->json([
                'message' => 'Cannot cancel an approved purchase',
            ], 422);
        }

        $creditPurchase->update([
            'status' => CreditPurchaseStatus::CANCELLED,
        ]);

        return response()->json([
            'data' => $creditPurchase->load(['wallet.client', 'customer', 'payments']),
            'message' => 'Purchase cancelled successfully',
        ]);
    }
}
