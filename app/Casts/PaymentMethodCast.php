<?php

namespace App\Casts;

use App\PaymentMethods\AbstractPaymentMethod;
use App\PaymentMethods\PaymentMethodRegistry;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class PaymentMethodCast implements CastsAttributes
{
    /**
     * Cast the stored string key to a PaymentMethod instance.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?AbstractPaymentMethod
    {
        if ($value === null) {
            return null;
        }

        return PaymentMethodRegistry::find((string) $value);
    }

    /**
     * Cast a PaymentMethod instance (or string) back to its string key for storage.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof AbstractPaymentMethod) {
            return $value->key();
        }

        return (string) $value;
    }
}
