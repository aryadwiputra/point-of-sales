<?php

namespace App\Support\Dine;

use App\Models\DineBill;
use App\Models\Transaction;

class DineSettlementResult
{
    public function __construct(
        public readonly Transaction $transaction,
        public readonly DineBill $bill,
        public readonly int $subtotal,
        public readonly int $discountTotal,
        public readonly int $taxTotal,
        public readonly int $grandTotal,
        public readonly bool $needsDiscountApproval,
    ) {}
}
