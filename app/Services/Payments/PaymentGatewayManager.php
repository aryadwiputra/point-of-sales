<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentGatewayException;
use App\Models\PaymentSetting;
use App\Models\Transaction;

class PaymentGatewayManager
{
    public function __construct(
        private MidtransGateway $midtransGateway,
        private XenditGateway $xenditGateway
    ) {
    }

    public function createPayment(Transaction $transaction, string $gateway, PaymentSetting $setting): array
    {
        return match ($gateway) {
            PaymentSetting::GATEWAY_MIDTRANS => $this->midtransGateway->createCharge($transaction, $setting->midtransConfig()),
            PaymentSetting::GATEWAY_XENDIT => $this->xenditGateway->createInvoice($transaction, $setting->xenditConfig()),
            default => throw new PaymentGatewayException("Gateway {$gateway} belum didukung."),
        };
    }
}
