<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $wallet_id
 * @property string|null $title
 * @property string|null $description
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property int|null $ledger_entry_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TimerCycle> $cycles
 * @property-read int|null $cycles_count
 * @property-read string $formatted_duration
 * @property-read float $total_hours
 * @property-read int $total_seconds
 * @property-read LedgerEntry|null $ledgerEntry
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Tag> $tags
 * @property-read int|null $tags_count
 * @property-read User $user
 * @property-read Wallet $wallet
 * @method static \Database\Factories\TimerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timer whereConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timer whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timer whereLedgerEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timer whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timer whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timer whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timer whereWalletId($value)
 * @mixin \Eloquent
 */
class Timer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wallet_id',
        'title',
        'description',
        'status',
        'confirmed_at',
        'ledger_entry_id',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    protected $appends = [
        'total_seconds',
        'formatted_duration',
        'total_hours',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(TimerCycle::class);
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'timer_tag');
    }

    public function getTotalSecondsAttribute(): int
    {
        $totalSeconds = 0;

        foreach ($this->cycles as $cycle) {
            if ($cycle->ended_at) {
                $totalSeconds += $cycle->duration_seconds;
            } else {
                // Ciclo em andamento: contabiliza tempo decorrido desde o início
                $totalSeconds += (int) $cycle->started_at->diffInSeconds(now());
            }
        }

        return $totalSeconds;
    }

    public function getFormattedDurationAttribute(): string
    {
        $totalSeconds = $this->total_seconds;

        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function getTotalHoursAttribute(): float
    {
        $totalSeconds = $this->total_seconds;

        return round($totalSeconds / 3600, 2);
    }
}
