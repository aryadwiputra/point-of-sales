<?php

namespace App\Support\Dine;

use App\Models\Customer;
use App\Models\CustomerVoucher;

class DineSettlementContext
{
    public function __construct(
        public readonly int $userId,
        public readonly ?Customer $customer = null,
        public readonly ?CustomerVoucher $voucher = null,
        public readonly int $manualDiscount = 0,
        public readonly int $requestedRedeemPoints = 0,
        public readonly bool $isPayLater = false,
        public readonly ?string $dueDate = null,
        public readonly bool $isCashPayment = true,
        public readonly int $cashAmount = 0,
        public readonly ?string $paymentGateway = null,
        public readonly bool $useTenders = false,
        public readonly array $tenderInput = [],
        public readonly ?int $bankAccountId = null,
        public readonly ?string $note = null,
    ) {}
}
