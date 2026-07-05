<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $payment_method_key
 * @property string $label
 * @property bool $is_active
 * @property string|null $instructions
 * @property int $display_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @mixin \Eloquent
 */
class PaymentMethodConfig extends Model
{
    protected $fillable = [
        'payment_method_key',
        'label',
        'is_active',
        'instructions',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function instructions(): ?string
    {
        return $this->instructions;
    }

    public function displayOrder(): int
    {
        return $this->display_order;
    }
}
