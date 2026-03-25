<?php

namespace App\Models;

use App\Enums\CreditPurchaseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $wallet_id
 * @property int $customer_id
 * @property numeric $total_hours
 * @property numeric $total_price
 * @property string $currency_code
 * @property CreditPurchaseStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CreditPurchasePayment> $payments
 * @property-read int|null $payments_count
 * @property-read Wallet $wallet
 * @method static \Database\Factories\CreditPurchaseFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchase newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchase newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchase query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchase whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchase whereCurrencyCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchase whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchase whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchase whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchase whereTotalHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchase whereTotalPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchase whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditPurchase whereWalletId($value)
 * @mixin \Eloquent
 */
class CreditPurchase extends Model
{
    use HasFactory;
    protected $fillable = [
        'wallet_id',
        'customer_id',
        'total_hours',
        'total_price',
        'currency_code',
        'status',
    ];

    protected $casts = [
        'total_hours' => 'decimal:2',
        'total_price' => 'decimal:2',
        'status' => CreditPurchaseStatus::class,
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CreditPurchasePayment::class);
    }
}
