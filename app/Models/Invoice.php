<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $client_id
 * @property int $invoice_number
 * @property int $client_invoice_number
 * @property \Illuminate\Support\Carbon $issue_date
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property string $currency
 * @property float $subtotal
 * @property float $tax_amount
 * @property float $tax_percentage
 * @property float $discount_amount
 * @property float $total
 * @property string $status
 * @property bool $hidden
 * @property string|null $notes
 * @property array|null $issued_by
 * @property array|null $bill_to
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Client $client
 * @property-read \Illuminate\Database\Eloquent\Collection<int, InvoiceItem> $items
 * @property-read int|null $items_count
 */
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'invoice_number',
        'client_invoice_number',
        'issue_date',
        'due_date',
        'currency',
        'subtotal',
        'tax_amount',
        'tax_percentage',
        'discount_amount',
        'total',
        'status',
        'hidden',
        'notes',
        'issued_by',
        'bill_to',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'hidden' => 'boolean',
        'issued_by' => 'array',
        'bill_to' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('line_number');
    }

    public function recalculateTotals(): void
    {
        $itemsTotal = $this->items()->sum('line_total');

        $this->subtotal = $itemsTotal;
        $this->tax_amount = ($itemsTotal * $this->tax_percentage) / 100;
        $this->total = $this->subtotal + $this->tax_amount - $this->discount_amount;
    }

    public function canBeViewedByCustomer(): bool
    {
        if ($this->hidden) {
            return false;
        }

        if ($this->status === 'draft') {
            return false;
        }

        return true;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (!$invoice->invoice_number) {
                $invoice->invoice_number = static::getNextInvoiceNumber();
            }

            if (!$invoice->client_invoice_number) {
                $invoice->client_invoice_number = static::getNextClientInvoiceNumber($invoice->client_id);
            }
        });
    }

    public static function getNextInvoiceNumber(): int
    {
        $lastInvoice = static::query()->orderBy('invoice_number', 'desc')->first();

        if (!$lastInvoice) {
            return 1;
        }

        return $lastInvoice->invoice_number + 1;
    }

    public static function getNextClientInvoiceNumber(int $clientId): int
    {
        $lastInvoice = static::query()
            ->where('client_id', $clientId)
            ->orderBy('client_invoice_number', 'desc')
            ->first();

        if (!$lastInvoice) {
            return 1;
        }

        return $lastInvoice->client_invoice_number + 1;
    }
}
