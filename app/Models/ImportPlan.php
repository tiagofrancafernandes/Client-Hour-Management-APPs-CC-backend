<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property int $user_id
 * @property int $wallet_id
 * @property string $original_filename
 * @property string $file_path
 * @property string $status
 * @property array<array-key, mixed>|null $summary
 * @property array<array-key, mixed>|null $validation_errors
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LedgerEntry> $ledgerEntries
 * @property-read int|null $ledger_entries_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ImportPlanRow> $rows
 * @property-read int|null $rows_count
 * @property-read User $user
 * @property-read Wallet $wallet
 * @method static \Database\Factories\ImportPlanFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlan whereConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlan whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlan whereOriginalFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlan whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlan whereSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlan whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlan whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlan whereValidationErrors($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlan whereWalletId($value)
 * @mixin \Eloquent
 */
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
