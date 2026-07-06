<?php

namespace App\PaymentMethods;

class PixOfflinePaymentMethod extends AbstractPaymentMethod
{
    public function key(): string
    {
        return 'pix_offline';
    }

    public function label(): string
    {
        return 'PIX Offline';
    }

    public function isOffline(): bool
    {
        return true;
    }

    public function currency(): ?string
    {
        return 'BRL';
    }

    /**
     * Expires in 48 hours after method is assigned.
     */
    public function expiresTime(): string|int|null
    {
        return '+48 hours';
    }

    public function setupFieldRules(): array
    {
        return [
            [
                'label' => 'PIX key',
                'name' => 'pix_key',
                'required' => true,
                'type' => 'string',
                'min' => 4,
                'max' => 25,
            ],
            [
                'label' => 'Receiver name',
                'name' => 'receiver_name',
                'required' => true,
                'type' => 'string',
                'min' => 4,
                'max' => 25,
            ],
            [
                'label' => 'Receiver city',
                'name' => 'receiver_city_name',
                'required' => true,
                'type' => 'string',
                'min' => 4,
                'max' => 15,
            ],
        ];
    }

    public function setupFieldDefaultValues(): array
    {
        return [
            'receiver_city_name' => 'São Paulo',
        ];
    }
}
