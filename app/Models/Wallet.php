<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $client_id
 * @property string $name
 * @property string|null $description
 * @property numeric|null $hourly_rate_reference
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $currency_code
 * @property string|null $internal_note
 * @property bool $credit_purchase_allowed
 * @property bool $discounts_enabled
 * @property array $discounts_config
 * @property-read Client $client
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CreditPurchase> $creditPurchases
 * @property-read int|null $credit_purchases_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LedgerEntry> $ledgerEntries
 * @property-read int|null $ledger_entries_count
 * @method static \Database\Factories\WalletFactory factory($count = null, $state = [])
 * @method static Builder<static>|Wallet forCustomer(\App\Models\User $user)
 * @method static Builder<static>|Wallet newModelQuery()
 * @method static Builder<static>|Wallet newQuery()
 * @method static Builder<static>|Wallet query()
 * @method static Builder<static>|Wallet whereClientId($value)
 * @method static Builder<static>|Wallet whereCreatedAt($value)
 * @method static Builder<static>|Wallet whereCreditPurchaseAllowed($value)
 * @method static Builder<static>|Wallet whereCurrencyCode($value)
 * @method static Builder<static>|Wallet whereDescription($value)
 * @method static Builder<static>|Wallet whereHourlyRateReference($value)
 * @method static Builder<static>|Wallet whereId($value)
 * @method static Builder<static>|Wallet whereInternalNote($value)
 * @method static Builder<static>|Wallet whereName($value)
 * @method static Builder<static>|Wallet whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Wallet extends Model
{
    use HasFactory;

    public const DEFAULT_DISCOUNTS_CONFIG = [
        '5h' => 0,
        '10h' => 10,
        '15h' => 15,
        'greater_than_15h' => 20,
    ];

    protected $fillable = [
        'client_id',
        'name',
        'description',
        'hourly_rate_reference',
        'currency_code',
        'internal_note',
        'credit_purchase_allowed',
        'discounts_enabled',
        'discounts_config',
    ];

    protected $casts = [
        'hourly_rate_reference' => 'decimal:2',
        'credit_purchase_allowed' => 'boolean',
        'discounts_enabled' => 'boolean',
        'discounts_config' => 'array',
    ];

    public function getDiscountsConfigAttribute($value): array
    {
        if ($value === null) {
            return self::DEFAULT_DISCOUNTS_CONFIG;
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;

        if (!is_array($decoded)) {
            return self::DEFAULT_DISCOUNTS_CONFIG;
        }

        if (isset($decoded['greater than 15h']) && !isset($decoded['greater_than_15h'])) {
            $decoded['greater_than_15h'] = $decoded['greater than 15h'];
        }

        return array_merge(self::DEFAULT_DISCOUNTS_CONFIG, $decoded);
    }

    public function setDiscountsConfigAttribute($value): void
    {
        if (is_null($value)) {
            $this->attributes['discounts_config'] = null;

            return;
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (is_array($value)) {
            if (isset($value['greater than 15h']) && !isset($value['greater_than_15h'])) {
                $value['greater_than_15h'] = $value['greater than 15h'];
                unset($value['greater than 15h']);
            }

            $this->attributes['discounts_config'] = json_encode($value);

            return;
        }

        $this->attributes['discounts_config'] = null;
    }

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

    public function scopeForCustomer(Builder $query, User $user): Builder
    {
        if (!$user || !$user->customer_id) {
            return $query->whereRaw('false');
        }

        return $query->where('client_id', $user->customer_id);
    }

    public function canAccessAsCustomer(User $user): bool
    {
        return $this->client_id === $user->customer_id;
    }
}
