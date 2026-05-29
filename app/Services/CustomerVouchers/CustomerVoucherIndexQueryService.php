<?php

declare(strict_types=1);

namespace App\Services\CustomerVouchers;

use App\Models\CustomerVoucher;

class CustomerVoucherIndexQueryService
{
    public function execute(array $filters): array
    {
        $vouchers = CustomerVoucher::query()
            ->with(['customer:id,name,no_telp', 'creator:id,name'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%')
                        ->orWhereHas(
                            'customer',
                            fn ($customerQuery) => $customerQuery->where('name', 'like', '%'.$search.'%')
                        );
                });
            })
            ->when($filters['status'] ?? null, function ($query, $status) {
                match ($status) {
                    'active' => $query->where('is_active', true)->where('is_used', false),
                    'scheduled' => $query->where('is_active', true)
                        ->where('is_used', false)
                        ->whereNotNull('starts_at')
                        ->where('starts_at', '>', now()),
                    'expired' => $query->whereNotNull('expires_at')->where('expires_at', '<', now()),
                    'used' => $query->where('is_used', true),
                    'inactive' => $query->where('is_active', false),
                    default => null,
                };
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return [
            'vouchers' => $vouchers,
            'filters' => $filters,
        ];
    }
}
