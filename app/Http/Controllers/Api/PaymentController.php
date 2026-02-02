<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\CreditPurchase;
use App\Models\CreditPurchasePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Create payment for a credit purchase
     * POST /api/credit-purchases/{id}/payments
     */
    public function store(Request $request, CreditPurchase $creditPurchase): JsonResponse
    {
        $user = auth()->user();

        // Verificar autorização
        if ($user->hasRole('customer') && $user->id !== $creditPurchase->customer_id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $request->validate([
            'payment_method' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (! in_array($value, ['pix_offline', 'bank_transfer'])) {
                        $fail('Invalid payment method');
                    }
                },
            ],
        ]);

        // Verificar se já existe pagamento
        if ($creditPurchase->payments()->exists()) {
            return response()->json([
                'message' => 'Payment already exists for this purchase',
            ], 422);
        }

        $paymentMethod = PaymentMethod::from($request->input('payment_method'));

        // Determinar status inicial baseado no método
        $paymentStatus = $paymentMethod === PaymentMethod::BANK_TRANSFER
            ? PaymentStatus::COMPLETED
            : PaymentStatus::PENDING;

        // Criar payment
        $payment = CreditPurchasePayment::create([
            'credit_purchase_id' => $creditPurchase->id,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
        ]);

        return response()->json([
            'data' => $payment,
            'message' => 'Payment created successfully',
        ], 201);
    }
}
