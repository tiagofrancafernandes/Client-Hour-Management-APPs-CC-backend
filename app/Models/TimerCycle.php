<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $timer_id
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int $duration_seconds
 * @property-read Timer $timer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimerCycle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimerCycle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimerCycle query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimerCycle whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimerCycle whereEndedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimerCycle whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimerCycle whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimerCycle whereTimerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TimerCycle whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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

        return $this->started_at->diffInSeconds($this->ended_at);
    }
}
