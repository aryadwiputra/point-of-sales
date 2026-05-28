<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Models\Supplier;

class DeleteSupplierService
{
    public function execute(Supplier $supplier): bool
    {
        if ($supplier->payables()->exists()) {
            return false;
        }

        $supplier->delete();

        return true;
    }
}
