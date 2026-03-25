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
     * Approve a payment
     * POST /api/payments/{id}/approve
     */
    public function approve(Request $request, CreditPurchasePayment $creditPurchasePayment): JsonResponse
    {
        $user = auth()->user();

        if (! $user->hasPermissionTo('credit_purchase.approve')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($creditPurchasePayment->isExpired()) {
            return response()->json(['message' => 'Payment has expired and cannot be approved'], 422);
        }

        if ($creditPurchasePayment->payment_status !== PaymentStatus::PENDING) {
            return response()->json(['message' => 'Payment is not pending'], 422);
        }

        $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $creditPurchasePayment->update([
            'payment_status' => PaymentStatus::APPROVED,
            'receipt_approved_by' => $user->id,
            'receipt_approved_at' => now(),
            'notes' => $request->input('notes'),
        ]);

        $creditPurchase = $creditPurchasePayment->creditPurchase;
        $this->applyCreditToWallet($creditPurchase);

        $creditPurchase->update([
            'status' => CreditPurchaseStatus::APPROVED,
        ]);

        return response()->json([
            'data' => $creditPurchasePayment->fresh(),
            'message' => 'Payment approved successfully',
        ]);
    }

    /**
     * Reject a payment
     * POST /api/payments/{id}/reject
     */
    public function reject(Request $request, CreditPurchasePayment $creditPurchasePayment): JsonResponse
    {
        $user = auth()->user();

        if (! $user->hasPermissionTo('credit_purchase.approve')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($creditPurchasePayment->payment_status !== PaymentStatus::PENDING) {
            return response()->json(['message' => 'Payment is not pending'], 422);
        }

        $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        $creditPurchasePayment->update([
            'payment_status' => PaymentStatus::REJECTED,
            'receipt_approved_by' => $user->id,
            'receipt_approved_at' => now(),
            'notes' => $request->input('notes'),
        ]);

        $creditPurchase = $creditPurchasePayment->creditPurchase;
        $creditPurchase->update([
            'status' => CreditPurchaseStatus::REJECTED,
        ]);

        return response()->json([
            'data' => $creditPurchasePayment->fresh(),
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

        if (! $user->hasPermissionTo('credit_purchase.approve')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $payments = CreditPurchasePayment::where('payment_status', PaymentStatus::PENDING)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->with(['creditPurchase.wallet', 'creditPurchase.customer'])
            ->latest()
            ->paginate(15);

        return response()->json($payments);
    }

    private function applyCreditToWallet(CreditPurchase $creditPurchase): void
    {
        $ledgerService = app('App\Services\LedgerService');
        $wallet = $creditPurchase->wallet;

        $ledgerService->addCredit($wallet, [
            'hours' => $creditPurchase->total_hours,
            'title' => "Credit Purchase - {$creditPurchase->total_hours}h",
            'description' => 'Hours purchased and approved',
            'reference_date' => now()->toDateString(),
        ]);
    }
}
