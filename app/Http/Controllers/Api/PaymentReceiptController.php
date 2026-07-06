<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditPurchase;
use App\Models\CreditPurchasePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentReceiptController extends Controller
{
    /**
     * Upload receipt for PIX Offline payment
     * POST /api/credit-purchases/{id}/payments/{payment}/upload-receipt
     */
    public function store(Request $request, CreditPurchase $creditPurchase, CreditPurchasePayment $creditPurchasePayment): JsonResponse
    {
        $user = auth()->user();

        if ($user->hasRole('customer') && $user->id !== $creditPurchase->customer_id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($creditPurchasePayment->credit_purchase_id !== $creditPurchase->id) {
            return response()->json([
                'message' => 'Payment does not belong to this purchase',
            ], 422);
        }

        $request->validate([
            'receipt' => [
                'required',
                'file',
                'max:5120',
                'mimes:pdf,png,jpg,jpeg',
            ],
        ]);

        $file = $request->file('receipt');
        $path = "pix-receipts/{$creditPurchase->id}/" . uniqid() . '.' . $file->getClientOriginalExtension();

        Storage::disk('local')->putFileAs(
            dirname($path),
            $file,
            basename($path),
            'private'
        );

        $creditPurchasePayment->update([
            'pix_receipt_path' => $path,
        ]);

        return response()->json([
            'data' => $creditPurchasePayment,
            'message' => 'Receipt uploaded successfully',
        ]);
    }

    /**
     * Download the receipt file (authenticated, streamed).
     * GET /api/payments/{id}/receipt-download
     */
    public function download(CreditPurchasePayment $creditPurchasePayment): StreamedResponse|JsonResponse
    {
        $user = auth()->user();

        if ($user->hasRole('customer') && $user->id !== $creditPurchasePayment->creditPurchase->customer_id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        if (! $creditPurchasePayment->pix_receipt_path) {
            return response()->json([
                'message' => 'No receipt uploaded',
            ], 404);
        }

        if (! Storage::disk('local')->exists($creditPurchasePayment->pix_receipt_path)) {
            return response()->json([
                'message' => 'Receipt file not found on disk',
            ], 404);
        }

        return Storage::disk('local')->download($creditPurchasePayment->pix_receipt_path);
    }

    /**
     * Delete receipt from a pending payment.
     * DELETE /api/credit-purchases/{id}/payments/{payment}/receipt
     */
    public function destroy(
        CreditPurchase $creditPurchase,
        CreditPurchasePayment $creditPurchasePayment
    ): JsonResponse {
        $user = auth()->user();

        if ($user->hasRole('customer') && $user->id !== $creditPurchase->customer_id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($creditPurchasePayment->credit_purchase_id !== $creditPurchase->id) {
            return response()->json([
                'message' => 'Payment does not belong to this purchase',
            ], 422);
        }

        if ($creditPurchasePayment->payment_status !== PaymentStatus::PENDING) {
            return response()->json([
                'message' => 'Can only delete receipt from pending payments',
            ], 422);
        }

        if ($creditPurchasePayment->isExpired()) {
            return response()->json([
                'message' => 'Cannot delete receipt from expired payment',
            ], 422);
        }

        if (! $creditPurchasePayment->pix_receipt_path) {
            return response()->json([
                'message' => 'No receipt to delete',
            ], 404);
        }

        if (Storage::disk('local')->exists($creditPurchasePayment->pix_receipt_path)) {
            Storage::disk('local')->delete($creditPurchasePayment->pix_receipt_path);
        }

        $creditPurchasePayment->update([
            'pix_receipt_path' => null,
        ]);

        return response()->json([
            'data' => $creditPurchasePayment,
            'message' => 'Receipt deleted successfully',
        ]);
    }
}
