<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InventoryReconcileCommand extends Command
{
    protected $signature = 'inventory:reconcile {--fix : Set products.stock to the sum of per-warehouse stock}';

    protected $description = 'Compare legacy products.stock against the per-warehouse pivot total and report (or fix) mismatches';

    public function handle(): int
    {
        $rows = Product::query()
            ->select('products.id', 'products.title', 'products.stock')
            ->selectRaw('COALESCE(SUM(product_warehouse.stock), 0) AS pivot_total')
            ->leftJoin('product_warehouse', 'product_warehouse.product_id', '=', 'products.id')
            ->groupBy('products.id', 'products.title', 'products.stock')
            ->get();

        $mismatches = $rows->filter(fn ($row) => (int) $row->stock !== (int) $row->pivot_total);

        if ($mismatches->isEmpty()) {
            $this->info('Inventory is consistent (products.stock matches per-warehouse totals).');

            return self::SUCCESS;
        }

        $this->warn("Found {$mismatches->count()} product(s) where products.stock differs from the per-warehouse total.");

        foreach ($mismatches as $row) {
            $this->line(sprintf(
                '  #%d %s — products.stock: %d, pivot total: %d',
                $row->id,
                $row->title,
                (int) $row->stock,
                (int) $row->pivot_total
            ));
        }

        if (! $this->option('fix')) {
            $this->info('Run with --fix to align products.stock to the pivot totals.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($mismatches) {
            foreach ($mismatches as $row) {
                Product::whereKey($row->id)->update(['stock' => (int) $row->pivot_total]);
            }
        });

        $this->info('Fixed: products.stock aligned to per-warehouse totals.');

        return self::SUCCESS;
    }
}
