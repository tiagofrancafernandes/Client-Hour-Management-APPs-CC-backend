<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int $line_number
 * @property string $description
 * @property string $unit
 * @property float $quantity
 * @property float $unit_price
 * @property float $line_total
 * @property int|null $product_service_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Invoice $invoice
 * @property-read ProductService|null $productService
 */
class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'line_number',
        'description',
        'unit',
        'quantity',
        'unit_price',
        'line_total',
        'product_service_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function productService(): BelongsTo
    {
        return $this->belongsTo(ProductService::class);
    }

    public function recalculateLineTotal(): void
    {
        $this->line_total = $this->quantity * $this->unit_price;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            if (!$item->line_number) {
                $item->line_number = static::getNextLineNumber($item->invoice_id);
            }

            $item->recalculateLineTotal();
        });

        static::updating(function ($item) {
            $item->recalculateLineTotal();
        });

        static::saved(function ($item) {
            $item->invoice->recalculateTotals();
            $item->invoice->save();
        });

        static::deleted(function ($item) {
            $item->invoice->recalculateTotals();
            $item->invoice->save();
        });
    }

    public static function getNextLineNumber(int $invoiceId): int
    {
        $lastItem = static::query()
            ->where('invoice_id', $invoiceId)
            ->orderBy('line_number', 'desc')
            ->first();

        if (!$lastItem) {
            return 1;
        }

        return $lastItem->line_number + 1;
    }
}
