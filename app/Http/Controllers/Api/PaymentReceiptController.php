<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditPurchase;
use App\Models\CreditPurchasePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentReceiptController extends Controller
{
    /**
     * Upload receipt for PIX Offline payment
     * POST /api/credit-purchases/{id}/payments/{payment}/upload-receipt
     */
    public function store(Request $request, CreditPurchase $creditPurchase, CreditPurchasePayment $payment): JsonResponse
    {
        $user = auth()->user();

        // Verificar autorização
        if ($user->hasRole('customer') && $user->id !== $creditPurchase->customer_id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        // Verificar se payment pertence a purchase
        if ($payment->credit_purchase_id !== $creditPurchase->id) {
            return response()->json([
                'message' => 'Payment does not belong to this purchase',
            ], 422);
        }

        // Validar arquivo
        $request->validate([
            'receipt' => [
                'required',
                'file',
                'max:5120', // 5MB
                'mimes:pdf,png,jpg,jpeg',
            ],
        ]);

        // Armazenar arquivo
        $file = $request->file('receipt');
        $path = "pix-receipts/{$creditPurchase->id}/" . uniqid() . '.' . $file->getClientOriginalExtension();

        Storage::disk('local')->putFileAs(
            dirname($path),
            $file,
            basename($path),
            'private'
        );

        // Atualizar payment
        $payment->update([
            'pix_receipt_path' => $path,
        ]);

        return response()->json([
            'data' => $payment,
            'message' => 'Receipt uploaded successfully',
        ]);
    }

    /**
     * Get receipt URL for download
     * GET /api/payments/{id}/receipt-url
     */
    public function getUrl(CreditPurchasePayment $payment): JsonResponse
    {
        $user = auth()->user();

        // Verificar autorização (owner ou admin)
        if ($user->hasRole('customer') && $user->id !== $payment->creditPurchase->customer_id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        if (! $payment->pix_receipt_path) {
            return response()->json([
                'message' => 'No receipt uploaded',
            ], 404);
        }

        $url = Storage::disk('local')->temporaryUrl(
            $payment->pix_receipt_path,
            now()->addMinutes(60),
            ['ResponseContentDisposition' => 'attachment']
        );

        return response()->json([
            'url' => $url,
        ]);
    }
}
