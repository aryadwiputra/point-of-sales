<?php

declare(strict_types=1);

namespace App\Services\StockOpnames;

use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Validation\ValidationException;

class StockOpnameGuardService
{
    public function ensureDraft(StockOpname $stockOpname): void
    {
        if (! $stockOpname->isDraft()) {
            throw ValidationException::withMessages([
                'stock_opname' => 'Sesi stock opname yang sudah final tidak dapat diubah.',
            ]);
        }
    }

    public function ensureItemBelongsToOpname(StockOpname $stockOpname, StockOpnameItem $item): void
    {
        if ($item->stock_opname_id !== $stockOpname->id) {
            abort(404);
        }
    }
}
