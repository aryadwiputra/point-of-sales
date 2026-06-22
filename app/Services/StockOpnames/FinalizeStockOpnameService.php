<?php

declare(strict_types=1);

namespace App\Services\StockOpnames;

use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Services\AuditLogService;
use App\Services\StockMutationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeStockOpnameService
{
    public function __construct(
        private readonly StockMutationService $stockMutationService,
        private readonly AuditLogService $auditLogService,
        private readonly StockOpnameGuardService $guard
    ) {}

    public function execute(StockOpname $stockOpname, ?int $userId): void
    {
        $this->guard->ensureDraft($stockOpname);

        $stockOpname->load('items.product');
        $beforeStatus = $stockOpname->status;

        foreach ($stockOpname->items as $item) {
            if ($item->difference !== null && $item->difference !== 0 && blank($item->adjustment_reason)) {
                throw ValidationException::withMessages([
                    'finalize' => 'Masih ada item selisih yang belum memiliki alasan adjustment.',
                ]);
            }
        }

        DB::transaction(function () use ($stockOpname, $userId) {
            foreach ($stockOpname->items as $item) {
                if ($item->physical_stock === null) {
                    continue;
                }

                $product = $item->product()->lockForUpdate()->first();

                if (! $product) {
                    continue;
                }

                $stockBefore = (int) $product->stock;
                $stockAfter = (int) $item->physical_stock;

                $product->update([
                    'stock' => $stockAfter,
                ]);

                $this->stockMutationService->recordStockOpnameAdjustment(
                    product: $product,
                    stockOpname: $stockOpname,
                    stockBefore: $stockBefore,
                    stockAfter: $stockAfter,
                    reason: $item->adjustment_reason,
                    userId: $userId,
                );
            }

            $stockOpname->update([
                'status' => 'finalized',
                'finalized_by' => $userId,
                'finalized_at' => now(),
            ]);
        });

        $stockOpname->refresh();
        $stockOpname->load('items.product');

        $this->auditLogService->log(
            event: 'stock.opname.finalized',
            module: 'stock',
            auditable: $stockOpname,
            description: 'Stock opname difinalisasi.',
            before: ['status' => $beforeStatus],
            after: ['status' => $stockOpname->status],
            meta: [
                'code' => $stockOpname->code,
                'notes' => $stockOpname->notes,
                'items' => $stockOpname->items->map(fn (StockOpnameItem $item) => [
                    'product_id' => $item->product_id,
                    'product_title' => $item->product?->title,
                    'stock_before' => (int) $item->system_stock,
                    'stock_after' => $item->physical_stock !== null ? (int) $item->physical_stock : null,
                    'difference' => $item->difference !== null ? (int) $item->difference : null,
                    'reason' => $item->adjustment_reason,
                    'reference' => $stockOpname->code,
                ])->values()->all(),
            ],
        );
    }
}
