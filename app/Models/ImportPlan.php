<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ImportPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wallet_id',
        'original_filename',
        'file_path',
        'status',
        'summary',
        'validation_errors',
        'confirmed_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'validation_errors' => 'array',
        'confirmed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportPlanRow::class);
    }

    public function ledgerEntries(): HasManyThrough
    {
        return $this->hasManyThrough(
            LedgerEntry::class,
            ImportPlanRow::class,
            'import_plan_id',
            'id',
            'id',
            'ledger_entry_id'
        );
    }
}
