<?php

declare(strict_types=1);

namespace Tests\Unit\Payments;

use App\Enums\PaymentStatus;
use App\Services\Payments\Webhooks\PaymentWebhookStatusMapper;
use PHPUnit\Framework\TestCase;

class PaymentWebhookStatusMapperTest extends TestCase
{
    public function test_it_maps_midtrans_statuses(): void
    {
        $mapper = new PaymentWebhookStatusMapper;

        $this->assertSame(PaymentStatus::PAID, $mapper->fromMidtrans('settlement'));
        $this->assertSame(PaymentStatus::PAID, $mapper->fromMidtrans('capture'));
        $this->assertSame(PaymentStatus::FAILED, $mapper->fromMidtrans('deny'));
        $this->assertSame(PaymentStatus::FAILED, $mapper->fromMidtrans('settlement', 'challenge'));
        $this->assertSame(PaymentStatus::PENDING, $mapper->fromMidtrans('unknown'));
    }

    public function test_it_maps_xendit_statuses(): void
    {
        $mapper = new PaymentWebhookStatusMapper;

        $this->assertSame(PaymentStatus::PAID, $mapper->fromXendit('PAID'));
        $this->assertSame(PaymentStatus::PAID, $mapper->fromXendit('settled'));
        $this->assertSame(PaymentStatus::FAILED, $mapper->fromXendit('EXPIRED'));
        $this->assertSame(PaymentStatus::FAILED, $mapper->fromXendit('failed'));
        $this->assertSame(PaymentStatus::PENDING, $mapper->fromXendit('unknown'));
    }
}
