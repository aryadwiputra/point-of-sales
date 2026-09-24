<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Warehouse;
use App\Services\ReorderService;
use Illuminate\Console\Command;

class GenerateReorderCommand extends Command
{
    protected $signature = 'reorder:generate';

    protected $description = 'Create draft purchase orders from low-stock reorder points';

    public function handle(ReorderService $reorderService): int
    {
        // ponytail: first admin is the PO author; add per-warehouse assignment if needed
        $userId = User::role('super-admin')->orderBy('id')->value('id')
            ?? User::orderBy('id')->value('id');

        $warehouses = Warehouse::where('is_active', true)->orderBy('id')->get();

        if ($warehouses->isEmpty()) {
            $this->info('No active warehouses.');

            return self::SUCCESS;
        }

        $created = 0;
        $hadLowStock = false;

        foreach ($warehouses as $warehouse) {
            $products = $reorderService->getLowStockProducts($warehouse->id);

            if ($products->isEmpty()) {
                continue;
            }

            $hadLowStock = true;

            $order = $reorderService->createDraftPurchaseOrder($products, $userId, $warehouse->id);

            if (! $order) {
                continue;
            }

            $created++;
            $this->info("Draft purchase order #{$order->id} created for {$warehouse->name} with {$order->items->count()} item(s).");
        }

        if ($created === 0) {
            $this->info($hadLowStock
                ? 'Low-stock products found, but no reorder quantity to suggest.'
                : 'No low-stock products.');
        }

        return self::SUCCESS;
    }
}
