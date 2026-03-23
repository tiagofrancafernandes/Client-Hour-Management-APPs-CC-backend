<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
