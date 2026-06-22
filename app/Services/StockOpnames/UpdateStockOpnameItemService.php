<?php

declare(strict_types=1);

namespace App\Services\StockOpnames;

use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Validation\ValidationException;

class UpdateStockOpnameItemService
{
    public function __construct(
        private readonly StockOpnameGuardService $guard
    ) {}

    public function execute(StockOpname $stockOpname, StockOpnameItem $item, array $data): void
    {
        $this->guard->ensureDraft($stockOpname);
        $this->guard->ensureItemBelongsToOpname($stockOpname, $item);

        $physicalStock = $data['physical_stock'] ?? null;
        $difference = $physicalStock !== null
            ? $physicalStock - $item->system_stock
            : null;

        $adjustmentReason = $data['adjustment_reason'] ?? null;

        if ($difference !== null && $difference !== 0 && blank($adjustmentReason)) {
            throw ValidationException::withMessages([
                'adjustment_reason' => 'Alasan adjustment wajib diisi jika ada selisih stok.',
            ]);
        }

        if ($difference === 0) {
            $adjustmentReason = null;
        }

        $item->update([
            'physical_stock' => $physicalStock,
            'difference' => $difference,
            'adjustment_reason' => $adjustmentReason,
        ]);
    }
}
