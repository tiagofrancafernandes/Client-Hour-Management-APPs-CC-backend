<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditPurchase extends Model
{
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
