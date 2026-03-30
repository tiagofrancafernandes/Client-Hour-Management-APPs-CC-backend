<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $email
 * @property string $token
 * @property string $hash
 * @property string $type
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken whereHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class EmailVerificationToken extends Model
{
    protected $fillable = [
        'email',
        'token',
        'hash',
        'type',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'token',
        'hash',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return !$this->isExpired();
    }
}
