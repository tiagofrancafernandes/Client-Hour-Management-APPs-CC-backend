<?php

namespace App\Models;

use App\Casts\PaymentMethodCast;
use App\Enums\PaymentStatus;
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
        'expires_at',
    ];

    protected $casts = [
        'payment_method' => PaymentMethodCast::class,
        'payment_status' => PaymentStatus::class,
        'receipt_approved_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function creditPurchase(): BelongsTo
    {
        return $this->belongsTo(CreditPurchase::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receipt_approved_by');
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }
}
