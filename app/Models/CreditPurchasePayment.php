<?php

namespace App\Models;

use App\Casts\PaymentMethodCast;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $credit_purchase_id
 * @property \App\PaymentMethods\AbstractPaymentMethod|null $payment_method
 * @property PaymentStatus $payment_status
 * @property string|null $pix_receipt_path
 * @property int|null $receipt_approved_by
 * @property \Illuminate\Support\Carbon|null $receipt_approved_at
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property-read User|null $approvedBy
 * @property-read CreditPurchase $creditPurchase
 * @method static \Database\Factories\CreditPurchasePaymentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchasePayment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchasePayment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchasePayment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchasePayment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchasePayment whereCreditPurchaseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchasePayment whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchasePayment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchasePayment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchasePayment wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchasePayment wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchasePayment wherePixReceiptPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchasePayment whereReceiptApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchasePayment whereReceiptApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchasePayment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CreditPurchasePayment extends Model
{
    use HasFactory;
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
