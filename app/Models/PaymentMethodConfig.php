<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $payment_method_key
 * @property string $label
 * @property bool $is_active
 * @property string|null $instructions
 * @property array|null $setup_fields
 * @property int $display_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @mixin \Eloquent
 */
class PaymentMethodConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_method_key',
        'label',
        'is_active',
        'instructions',
        'setup_fields',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
        'instructions' => 'json',
        'setup_fields' => 'json',
    ];
}
