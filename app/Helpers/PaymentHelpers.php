<?php

namespace App\Helpers;

use Illuminate\Support\Collection;
use App\PaymentMethods\AbstractPaymentMethod;
use App\PaymentMethods\PaymentMethodRegistry;

class PaymentHelpers
{
    public static function getActiveOfflinePaymentMethods(): Collection
    {
        return static::getActivePaymentMethods()
            ->filter(
                fn (AbstractPaymentMethod $i) => $i->isOffline()
            )->mapWithKeys(fn (AbstractPaymentMethod $i) => [$i->key() => $i]);
    }

    public static function getOfflinePaymentMethods(): Collection
    {
        return static::getAllPaymentMethods()
            ->filter(
                fn (AbstractPaymentMethod $i) => $i->isOffline()
            )->mapWithKeys(fn (AbstractPaymentMethod $i) => [$i->key() => $i]);
    }

    public static function getActivePaymentMethods(): Collection
    {
        return collect(PaymentMethodRegistry::active());
    }

    public static function getAllPaymentMethods(): Collection
    {
        return collect(PaymentMethodRegistry::all());
    }
}
