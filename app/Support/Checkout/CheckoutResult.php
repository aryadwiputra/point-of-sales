<?php

namespace App\Support\Checkout;

use App\Models\Transaction;

class CheckoutResult
{
    public function __construct(
        public readonly Transaction $transaction,
        public readonly array $pricingPreview,
        public readonly array $checkoutPreview,
        public readonly bool $needsDiscountApproval,
        public readonly int $appliedManualDiscount,
        public readonly array $tenders,
        public readonly string $paymentMethod,
        public readonly string $paymentStatus,
        public readonly ?int $tenderBankAccountId,
    ) {}
}
