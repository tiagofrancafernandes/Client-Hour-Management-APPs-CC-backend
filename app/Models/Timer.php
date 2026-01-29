<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            $totalSeconds += $cycle->duration_seconds;
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
