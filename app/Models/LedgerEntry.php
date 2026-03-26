<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $wallet_id
 * @property numeric $hours
 * @property string|null $title
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $reference_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Tag> $tags
 * @property-read int|null $tags_count
 * @property-read Wallet $wallet
 * @method static \Database\Factories\LedgerEntryFactory factory($count = null, $state = [])
 * @method static Builder<static>|LedgerEntry forCustomer(\App\Models\User $user)
 * @method static Builder<static>|LedgerEntry newModelQuery()
 * @method static Builder<static>|LedgerEntry newQuery()
 * @method static Builder<static>|LedgerEntry query()
 * @method static Builder<static>|LedgerEntry whereCreatedAt($value)
 * @method static Builder<static>|LedgerEntry whereDescription($value)
 * @method static Builder<static>|LedgerEntry whereHours($value)
 * @method static Builder<static>|LedgerEntry whereId($value)
 * @method static Builder<static>|LedgerEntry whereReferenceDate($value)
 * @method static Builder<static>|LedgerEntry whereTitle($value)
 * @method static Builder<static>|LedgerEntry whereUpdatedAt($value)
 * @method static Builder<static>|LedgerEntry whereWalletId($value)
 * @mixin \Eloquent
 */
class LedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'hours',
        'title',
        'description',
        'reference_date',
    ];

    protected $casts = [
        'hours' => 'decimal:2',
        'reference_date' => 'date',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'ledger_entry_tag');
    }

    public function scopeForCustomer(Builder $query, User $user): Builder
    {
        if (!$user || !$user->customer_id) {
            return $query->whereRaw('false');
        }

        return $query->whereHas('wallet', function (Builder $q) use ($user) {
            $q->where('client_id', $user->customer_id);
        });
    }
}
