<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case PAID = 'paid';
    case PENDING = 'pending';
    case UNPAID = 'unpaid';
    case FAILED = 'failed';
}
