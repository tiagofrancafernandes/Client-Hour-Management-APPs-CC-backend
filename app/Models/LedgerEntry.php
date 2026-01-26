<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'hours',
        'title',
        'description',
        'reference_date',
    ];

    protected $casts = [
        'hours' => 'decimal:2',
        'reference_date' => 'date',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'ledger_entry_tag');
    }
}
