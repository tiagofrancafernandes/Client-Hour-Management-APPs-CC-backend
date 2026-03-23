<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
