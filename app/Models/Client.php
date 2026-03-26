<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $customer_since
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $users
 * @property-read int|null $users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Wallet> $wallets
 * @property-read int|null $wallets_count
 * @method static Builder<static>|Client byCustomer(\App\Models\User $user)
 * @method static \Database\Factories\ClientFactory factory($count = null, $state = [])
 * @method static Builder<static>|Client newModelQuery()
 * @method static Builder<static>|Client newQuery()
 * @method static Builder<static>|Client query()
 * @method static Builder<static>|Client whereCreatedAt($value)
 * @method static Builder<static>|Client whereCustomerSince($value)
 * @method static Builder<static>|Client whereId($value)
 * @method static Builder<static>|Client whereName($value)
 * @method static Builder<static>|Client whereNotes($value)
 * @method static Builder<static>|Client whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'notes',
        'customer_since',
    ];

    protected $casts = [
        'customer_since' => 'date',
    ];

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'customer_id');
    }

    public function isUserCustomer(User $user): bool
    {
        return $user->customer_id === $this->id;
    }

    public function scopeByCustomer(Builder $query, User $user): Builder
    {
        return $query->where('id', $user->customer_id);
    }
}
