<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $import_plan_id
 * @property int $row_number
 * @property \Illuminate\Support\Carbon $reference_date
 * @property numeric $hours
 * @property string $title
 * @property string|null $description
 * @property array<array-key, mixed>|null $tags
 * @property array<array-key, mixed>|null $validation_errors
 * @property bool $is_valid
 * @property int|null $ledger_entry_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $start_time
 * @property \Illuminate\Support\Carbon|null $end_time
 * @property string $input_type
 * @property-read ImportPlan $importPlan
 * @property-read LedgerEntry|null $ledgerEntry
 * @method static \Database\Factories\ImportPlanRowFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow whereHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow whereImportPlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow whereInputType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow whereIsValid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow whereLedgerEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow whereReferenceDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow whereRowNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow whereTags($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ImportPlanRow whereValidationErrors($value)
 * @mixin \Eloquent
 */
class ImportPlanRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_plan_id',
        'row_number',
        'reference_date',
        'start_time',
        'end_time',
        'hours',
        'title',
        'description',
        'tags',
        'input_type',
        'validation_errors',
        'is_valid',
        'ledger_entry_id',
    ];

    protected $casts = [
        'reference_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'hours' => 'decimal:2',
        'tags' => 'array',
        'validation_errors' => 'array',
        'is_valid' => 'boolean',
    ];

    public function importPlan(): BelongsTo
    {
        return $this->belongsTo(ImportPlan::class);
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }
}
