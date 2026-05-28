<?php

declare(strict_types=1);

namespace App\Services\SupplierReturns;

use App\Models\SupplierReturn;
use App\Services\SupplierReturnService;

class CreateSupplierReturnService
{
    public function __construct(
        private readonly SupplierReturnService $supplierReturnService
    ) {}

    public function execute(array $data, int $userId): SupplierReturn
    {
        return $this->supplierReturnService->createReturn($data, $data['items'], $userId);
    }
}
