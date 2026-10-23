<?php

namespace App\PaymentMethods;

class PixPaymentMethod extends AbstractPaymentMethod
{
    public function key(): string
    {
        return 'pix';
    }

    public function label(): string
    {
        return 'PIX';
    }

    public function isOffline(): bool
    {
        return false;
    }

    public function currency(): ?string
    {
        return 'BRL';
    }

    /**
     * Expires in 60 minutes after method is assigned.
     */
    public function expiresTime(): string|int|null
    {
        return '+60 minutes';
    }
}
