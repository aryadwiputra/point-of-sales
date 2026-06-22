<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Models\Supplier;

class UpdateSupplierService
{
    public function execute(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier->fresh();
    }
}
