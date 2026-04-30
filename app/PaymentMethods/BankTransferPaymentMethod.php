<?php

namespace App\PaymentMethods;

class BankTransferPaymentMethod extends AbstractPaymentMethod
{
    public function key(): string
    {
        return 'bank_transfer';
    }

    public function label(): string
    {
        return 'Bank Transfer';
    }

    public function isOffline(): bool
    {
        return true;
    }

    /**
     * Expires in 7 days after method is assigned.
     */
    public function expiresTime(): string|int|null
    {
        return '+7 days';
    }
}
