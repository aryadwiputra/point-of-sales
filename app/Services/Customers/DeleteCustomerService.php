<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Customer;

class DeleteCustomerService
{
    public function execute(Customer $customer): void
    {
        $customer->delete();
    }
}
