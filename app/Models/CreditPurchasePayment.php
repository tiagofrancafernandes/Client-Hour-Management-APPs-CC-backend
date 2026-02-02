<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditPurchasePayment extends Model
{
    protected $fillable = [
        'credit_purchase_id',
        'payment_method',
        'payment_status',
        'pix_receipt_path',
        'receipt_approved_by',
        'receipt_approved_at',
        'notes',
    ];

    protected $casts = [
        'receipt_approved_at' => 'datetime',
    ];

    public function creditPurchase(): BelongsTo
    {
        return $this->belongsTo(CreditPurchase::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receipt_approved_by');
    }
}
