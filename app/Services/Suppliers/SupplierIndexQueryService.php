<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;

class SupplierIndexQueryService
{
    public function execute(): Collection
    {
        return Supplier::query()
            ->orderBy('name')
            ->get();
    }
}
