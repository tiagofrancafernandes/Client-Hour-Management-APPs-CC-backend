<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\CreditPurchase;
use App\Models\CreditPurchasePayment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    // Expiration in hours per payment method
    private const EXPIRATION_HOURS = [
        'pix_offline' => 48,
        'bank_transfer' => 168, // 7 days
    ];

    /**
     * Create payment for a credit purchase
     * POST /api/credit-purchases/{creditPurchase}/payments
     */
    public function store(Request $request, CreditPurchase $creditPurchase): JsonResponse
    {
        $user = auth()->user();

        if ($user->hasRole('customer') && $user->id !== $creditPurchase->customer_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'payment_method' => ['nullable', 'string', 'in:pix_offline,bank_transfer'],
        ]);

        if ($creditPurchase->payments()->exists()) {
            return response()->json(['message' => 'Payment already exists for this purchase'], 422);
        }

        $methodValue = $request->input('payment_method');
        $paymentMethod = $methodValue ? PaymentMethod::from($methodValue) : null;
        $expiresAt = $this->calculateExpiration($methodValue);

        $payment = CreditPurchasePayment::create([
            'credit_purchase_id' => $creditPurchase->id,
            'payment_method' => $paymentMethod,
            'payment_status' => PaymentStatus::PENDING,
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'data' => $payment,
            'message' => 'Payment created successfully',
        ], 201);
    }

    /**
     * Update payment method for an existing payment
     * PUT /api/credit-purchases/{creditPurchase}/payments/{creditPurchasePayment}/set-method
     */
    public function setMethod(Request $request, CreditPurchase $creditPurchase, CreditPurchasePayment $creditPurchasePayment): JsonResponse
    {
        $user = auth()->user();

        if ($user->hasRole('customer') && $user->id !== $creditPurchase->customer_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($creditPurchasePayment->credit_purchase_id !== $creditPurchase->id) {
            return response()->json(['message' => 'Payment does not belong to this purchase'], 422);
        }

        if ($creditPurchasePayment->payment_status !== PaymentStatus::PENDING) {
            return response()->json(['message' => 'Cannot change method for a non-pending payment'], 422);
        }

        if ($creditPurchasePayment->isExpired()) {
            return response()->json(['message' => 'Payment has expired'], 422);
        }

        $request->validate([
            'payment_method' => ['required', 'string', 'in:pix_offline,bank_transfer'],
        ]);

        $expiresAt = $this->calculateExpiration($request->input('payment_method'));

        $creditPurchasePayment->update([
            'payment_method' => PaymentMethod::from($request->input('payment_method')),
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'data' => $creditPurchasePayment->fresh(),
            'message' => 'Payment method updated successfully',
        ]);
    }

    private function calculateExpiration(?string $paymentMethod): ?Carbon
    {
        if ($paymentMethod === null) {
            return null;
        }

        if (! isset(self::EXPIRATION_HOURS[$paymentMethod])) {
            return null;
        }

        return now()->addHours(self::EXPIRATION_HOURS[$paymentMethod]);
    }
}
