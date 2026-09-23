<?php

namespace App\Support\Checkout;

use App\Models\Customer;
use App\Models\CustomerVoucher;
use App\Models\Outlet;

class CheckoutContext
{
    public function __construct(
        public readonly int $userId,
        public readonly ?Customer $customer,
        public readonly ?CustomerVoucher $voucher,
        public readonly int $manualDiscount,
        public readonly int $shippingCost,
        public readonly int $requestedRedeemPoints,
        public readonly bool $isPayLater,
        public readonly ?string $dueDate,
        public readonly ?string $orderType,
        public readonly ?string $note,
        public readonly ?string $customerNpwp,
        public readonly bool $isCashPayment,
        public readonly int $cashAmount,
        public readonly ?string $paymentGateway,
        public readonly bool $useTenders,
        public readonly array $tenderInput,
        public readonly ?Outlet $outlet,
        public readonly ?int $bankAccountId,
        public readonly ?string $clientUuid = null,
        public readonly ?string $syncFingerprint = null,
        public readonly ?array $onlyCartIds = null,
    ) {}

    public function isSplit(): bool
    {
        return $this->useTenders;
    }
}
