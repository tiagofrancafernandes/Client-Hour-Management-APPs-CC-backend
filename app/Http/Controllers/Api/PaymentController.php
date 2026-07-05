<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\CreditPurchase;
use App\Models\CreditPurchasePayment;
use App\Models\PaymentMethodConfig;
use App\PaymentMethods\PaymentMethodRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Create payment for a credit purchase.
     * POST /api/credit-purchases/{creditPurchase}/payments
     */
    public function store(Request $request, CreditPurchase $creditPurchase): JsonResponse
    {
        $user = auth()->user();

        if ($user->hasRole('customer') && $user->id !== $creditPurchase->customer_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'payment_method' => [
                'nullable',
                'string',
                'in:' . implode(',', PaymentMethodRegistry::keys()),
            ],
        ]);

        if ($creditPurchase->payments()->exists()) {
            return response()->json(['message' => 'Payment already exists for this purchase'], 422);
        }

        $methodKey = $request->input('payment_method');
        $methodInstance = $methodKey ? PaymentMethodRegistry::find($methodKey) : null;

        $payment = CreditPurchasePayment::create([
            'credit_purchase_id' => $creditPurchase->id,
            'payment_method' => $methodKey,
            'payment_status' => PaymentStatus::PENDING,
            'expires_at' => $methodInstance?->expiresAt(),
        ]);

        return response()->json([
            'data' => $payment,
            'message' => 'Payment created successfully',
        ], 201);
    }

    /**
     * Update payment method for an existing pending payment.
     * PUT /api/credit-purchases/{creditPurchase}/payments/{creditPurchasePayment}/set-method
     */
    public function setMethod(
        Request $request,
        CreditPurchase $creditPurchase,
        CreditPurchasePayment $creditPurchasePayment
    ): JsonResponse {
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
            'payment_method' => [
                'required',
                'string',
                'in:' . implode(',', PaymentMethodRegistry::keys()),
            ],
        ]);

        $methodKey = $request->input('payment_method');
        $methodInstance = PaymentMethodRegistry::fromKey($methodKey);

        $creditPurchasePayment->update([
            'payment_method' => $methodKey,
            'expires_at' => $methodInstance->expiresAt(),
        ]);

        return response()->json([
            'data' => $creditPurchasePayment->fresh(),
            'message' => 'Payment method updated successfully',
        ]);
    }

    /**
     * Get all active payment methods with their configuration.
     * GET /api/payment-methods
     */
    public function getMethods(): JsonResponse
    {
        $activeMethods = PaymentMethodRegistry::active();

        $methodsWithConfig = array_map(
            static function ($method) {
                $config = PaymentMethodConfig::where('payment_method_key', $method->key())
                    ->first();

                $data = $method->toArray();

                if ($config) {
                    $data['is_active'] = $config->is_active;
                    $data['instructions'] = $config->instructions;
                    $data['display_order'] = $config->display_order;
                }

                return $data;
            },
            $activeMethods
        );

        // Ordenar por display_order
        usort($methodsWithConfig, static fn ($a, $b) => $a['display_order'] <=> $b['display_order']);

        return response()->json([
            'methods' => array_values($methodsWithConfig),
        ]);
    }
}
