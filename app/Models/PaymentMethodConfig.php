<?php

namespace App\Models;

use App\PaymentMethods\AbstractPaymentMethod;
use App\PaymentMethods\PaymentMethodRegistry;
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

    public function getPaymentMethodInstance(): ?AbstractPaymentMethod
    {
        return PaymentMethodRegistry::find($this->payment_method_key);
    }

    public function getSetupFieldRules(): array
    {
        $instance = $this->getPaymentMethodInstance();

        if ($instance === null) {
            return [];
        }

        return $instance->setupFieldRules();
    }

    public function getSetupFieldDefaults(): array
    {
        $instance = $this->getPaymentMethodInstance();

        if ($instance === null) {
            return [];
        }

        return $instance->setupFieldDefaultValues();
    }

    public function isSetupComplete(): bool
    {
        $rules = $this->getSetupFieldRules();

        if (empty($rules)) {
            return true;
        }

        $setupData = $this->setup_fields ?? [];

        foreach ($rules as $rule) {
            if ($rule['required'] ?? false) {
                $value = $setupData[$rule['name']] ?? null;

                if (empty($value)) {
                    return false;
                }
            }
        }

        return true;
    }
}
