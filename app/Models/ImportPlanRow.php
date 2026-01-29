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
        'hours',
        'title',
        'description',
        'tags',
        'validation_errors',
        'is_valid',
        'ledger_entry_id',
    ];

    protected $casts = [
        'reference_date' => 'date',
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
