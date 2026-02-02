<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case PIX_OFFLINE = 'pix_offline';
    case BANK_TRANSFER = 'bank_transfer';
}
