<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Models\Supplier;

class CreateSupplierService
{
    public function execute(array $data): Supplier
    {
        return Supplier::create($data);
    }
}
