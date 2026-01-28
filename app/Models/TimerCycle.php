<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimerCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'timer_id',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected $appends = [
        'duration_seconds',
    ];

    public function timer(): BelongsTo
    {
        return $this->belongsTo(Timer::class);
    }

    public function getDurationSecondsAttribute(): int
    {
        if (!$this->ended_at) {
            return 0;
        }

        return $this->ended_at->diffInSeconds($this->started_at);
    }
}
