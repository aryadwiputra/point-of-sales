<?php

declare(strict_types=1);

namespace App\Services\StockOpnames;

use App\Models\StockOpname;

class UpdateStockOpnameService
{
    public function __construct(
        private readonly StockOpnameGuardService $guard
    ) {}

    public function execute(StockOpname $stockOpname, array $data): void
    {
        $this->guard->ensureDraft($stockOpname);

        $stockOpname->update($data);
    }
}
