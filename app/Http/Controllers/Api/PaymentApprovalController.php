<?php

namespace App\Http\Controllers\Api;

use App\Enums\CreditPurchaseStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\CreditPurchase;
use App\Models\CreditPurchasePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentApprovalController extends Controller
{
    /**
     * Approve a payment (PIX Offline)
     * POST /api/payments/{id}/approve
     */
    public function approve(Request $request, CreditPurchasePayment $payment): JsonResponse
    {
        $user = auth()->user();

        // Verificar permissão de admin
        if (! $user->hasPermissionTo('credit_purchase.approve')) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        // Validar
        $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Atualizar pagamento
        $payment->update([
            'payment_status' => PaymentStatus::APPROVED,
            'receipt_approved_by' => $user->id,
            'receipt_approved_at' => now(),
            'notes' => $request->input('notes'),
        ]);

        // Aplicar crédito na wallet (criar LedgerEntry)
        $creditPurchase = $payment->creditPurchase;
        $this->applyCreditToWallet($creditPurchase);

        // Atualizar status da compra
        $creditPurchase->update([
            'status' => CreditPurchaseStatus::APPROVED,
        ]);

        return response()->json([
            'data' => $payment->fresh(),
            'message' => 'Payment approved successfully',
        ]);
    }

    /**
     * Reject a payment
     * POST /api/payments/{id}/reject
     */
    public function reject(Request $request, CreditPurchasePayment $payment): JsonResponse
    {
        $user = auth()->user();

        // Verificar permissão
        if (! $user->hasPermissionTo('credit_purchase.approve')) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        // Validar
        $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        // Atualizar pagamento
        $payment->update([
            'payment_status' => PaymentStatus::REJECTED,
            'receipt_approved_by' => $user->id,
            'receipt_approved_at' => now(),
            'notes' => $request->input('notes'),
        ]);

        // Atualizar status da compra
        $creditPurchase = $payment->creditPurchase;
        $creditPurchase->update([
            'status' => CreditPurchaseStatus::REJECTED,
        ]);

        return response()->json([
            'data' => $payment->fresh(),
            'message' => 'Payment rejected successfully',
        ]);
    }

    /**
     * List pending payments for approval
     * GET /api/payments/pending
     */
    public function pending(): JsonResponse
    {
        $user = auth()->user();

        // Verificar permissão
        if (! $user->hasPermissionTo('credit_purchase.approve')) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $payments = CreditPurchasePayment::where('payment_status', PaymentStatus::PENDING)
            ->with(['creditPurchase.wallet', 'creditPurchase.customer'])
            ->latest()
            ->paginate(15);

        return response()->json($payments);
    }

    /**
     * Aplicar crédito na wallet do cliente
     */
    private function applyCreditToWallet(CreditPurchase $creditPurchase): void
    {
        // Importar o serviço de ledger
        $ledgerService = app('App\Services\LedgerService');

        $wallet = $creditPurchase->wallet;
        $data = [
            'title' => "Credit Purchase - {$creditPurchase->total_hours}h",
            'description' => "Hours purchased and approved",
            'reference_date' => now()->toDateString(),
        ];

        // Criar entrada de crédito
        $ledgerService->addCredit($wallet, [
            'hours' => $creditPurchase->total_hours,
            'title' => $data['title'],
            'description' => $data['description'],
            'reference_date' => $data['reference_date'],
        ]);
    }
}
