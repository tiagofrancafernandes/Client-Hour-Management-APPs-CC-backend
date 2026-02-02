<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'description',
        'hourly_rate_reference',
        'currency_code',
        'internal_note',
        'credit_purchase_allowed',
    ];

    protected $casts = [
        'hourly_rate_reference' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function creditPurchases(): HasMany
    {
        return $this->hasMany(CreditPurchase::class);
    }

    /**
     * Remove internal_note attribute from model when user cannot view it.
     */
    public function hideInternalNoteIfNotPermitted(User $user): self
    {
        if (!$user || !$user->hasPermissionTo('wallet.view_internal_note')) {
            // Ensure attribute is not present when serializing
            if (array_key_exists('internal_note', $this->attributes)) {
                unset($this->attributes['internal_note']);
            }
        }

        return $this;
    }

    public function canViewInternalNote(User $user): bool
    {
        return $user && $user->hasPermissionTo('wallet.view_internal_note');
    }
}
