<?php

declare(strict_types=1);

namespace App\Services\CustomerVouchers;

use App\Models\Customer;
use App\Models\CustomerVoucher;

class CustomerVoucherPayloadService
{
    public function createPayload(): array
    {
        return [
            'customers' => $this->customerOptions(),
        ];
    }

    public function editPayload(CustomerVoucher $voucher): array
    {
        return [
            'voucher' => $voucher->load('customer:id,name,no_telp'),
            'customers' => $this->customerOptions(),
        ];
    }

    public function auditPayload(CustomerVoucher $voucher): array
    {
        return [
            'customer_id' => $voucher->customer_id,
            'code' => $voucher->code,
            'name' => $voucher->name,
            'discount_type' => $voucher->discount_type,
            'discount_value' => (float) $voucher->discount_value,
            'minimum_order' => (int) $voucher->minimum_order,
            'is_active' => (bool) $voucher->is_active,
            'is_used' => (bool) $voucher->is_used,
            'starts_at' => optional($voucher->starts_at)?->toIso8601String(),
            'expires_at' => optional($voucher->expires_at)?->toIso8601String(),
            'used_at' => optional($voucher->used_at)?->toIso8601String(),
        ];
    }

    private function customerOptions()
    {
        return Customer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'no_telp', 'is_loyalty_member', 'loyalty_tier', 'loyalty_points']);
    }
}
