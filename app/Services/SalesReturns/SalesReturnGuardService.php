<?php

declare(strict_types=1);

namespace App\Services\SalesReturns;

use App\Models\SalesReturn;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SalesReturnGuardService
{
    public function ensureTablesExist(): void
    {
        if (! Schema::hasTable('sales_returns') || ! Schema::hasTable('sales_return_items')) {
            abort(503, 'Fitur retur penjualan belum siap. Jalankan migrasi database terlebih dahulu.');
        }
    }

    public function ensureDraft(SalesReturn $salesReturn): void
    {
        if (! $salesReturn->isDraft()) {
            throw ValidationException::withMessages([
                'sales_return' => 'Retur penjualan yang sudah selesai tidak dapat diubah lagi.',
            ]);
        }
    }
}
