<?php

declare(strict_types=1);

namespace App\Services\SupplierReturns;

use App\Models\SupplierReturn;
use App\Services\SupplierReturnService;

class CompleteSupplierReturnService
{
    public function __construct(
        private readonly SupplierReturnService $supplierReturnService
    ) {}

    public function execute(SupplierReturn $supplierReturn): bool
    {
        if ($supplierReturn->status !== 'draft') {
            return false;
        }

        $this->supplierReturnService->complete($supplierReturn);

        return true;
    }
}
