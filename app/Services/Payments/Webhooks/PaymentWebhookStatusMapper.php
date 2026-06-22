<?php

declare(strict_types=1);

namespace App\Services\Payments\Webhooks;

use App\Enums\PaymentStatus;

class PaymentWebhookStatusMapper
{
    public function fromMidtrans(string $transactionStatus, ?string $fraudStatus = null): PaymentStatus
    {
        if (in_array($fraudStatus, ['challenge', 'deny'], true)) {
            return PaymentStatus::FAILED;
        }

        return match ($transactionStatus) {
            'capture', 'settlement' => PaymentStatus::PAID,
            'deny', 'cancel', 'expire' => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }

    public function fromXendit(string $status): PaymentStatus
    {
        return match (strtoupper($status)) {
            'PAID', 'SETTLED' => PaymentStatus::PAID,
            'EXPIRED', 'FAILED' => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }
}
