<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;

class CustomerIndexQueryService
{
    public function execute(?string $search): array
    {
        $customers = Customer::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($customerQuery) use ($search) {
                    $customerQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('member_code', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->paginate(5);

        return [
            'customers' => $customers,
        ];
    }
}
