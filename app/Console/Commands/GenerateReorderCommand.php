<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ReorderService;
use Illuminate\Console\Command;

class GenerateReorderCommand extends Command
{
    protected $signature = 'reorder:generate';

    protected $description = 'Create draft purchase orders from low-stock reorder points';

    public function handle(ReorderService $reorderService): int
    {
        $products = $reorderService->getLowStockProducts();

        if ($products->isEmpty()) {
            $this->info('No low-stock products.');

            return self::SUCCESS;
        }

        // ponytail: first admin is the PO author; add per-warehouse assignment if needed
        $userId = User::role('super-admin')->orderBy('id')->value('id')
            ?? User::orderBy('id')->value('id');

        $order = $reorderService->createDraftPurchaseOrder($products, $userId);

        if (! $order) {
            $this->info('Low-stock products found, but no reorder quantity to suggest.');

            return self::SUCCESS;
        }

        $this->info("Draft purchase order #{$order->id} created with {$order->items->count()} item(s).");

        return self::SUCCESS;
    }
}
