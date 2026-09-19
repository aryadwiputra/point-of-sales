<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditLegacyOutletCommand extends Command
{
    protected $signature = 'outlet:legacy-audit {--backfill : Fill only unambiguous stock mutation warehouse IDs}';

    protected $description = 'Classify warehouse-less records without changing data';

    public function handle(): int
    {
        $this->info($this->option('backfill')
            ? 'Legacy outlet audit (backfill mode; only unambiguous stock mutations will change)'
            : 'Legacy outlet audit (read-only; no backfill performed)');

        if ($this->option('backfill')) {
            $this->backfillStockMutations();
        }

        foreach ([
            'transactions',
            'carts',
            'stock_mutations',
            'cashier_shifts',
            'purchase_orders',
            'goods_receivings',
            'supplier_returns',
            'stock_opnames',
        ] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'warehouse_id')) {
                continue;
            }

            $count = DB::table($table)->whereNull('warehouse_id')->count();
            $this->line(sprintf('%-20s %d legacy rows', $table, $count));
        }

        $this->line('Action: map only confirmed records, then run outlet:audit --strict.');

        return self::SUCCESS;
    }

    private function backfillStockMutations(): void
    {
        $referenceTables = [
            'stock_opname' => 'stock_opnames',
            'sales_return' => 'sales_returns',
            'goods_receiving' => 'goods_receivings',
            'supplier_return' => 'supplier_returns',
        ];
        $updated = 0;
        $ambiguous = 0;

        DB::table('stock_mutations')
            ->whereNull('warehouse_id')
            ->orderBy('id')
            ->each(function ($mutation) use ($referenceTables, &$updated, &$ambiguous) {
                $table = $referenceTables[$mutation->reference_type] ?? null;
                if (! $table || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'warehouse_id')) {
                    $ambiguous++;

                    return;
                }

                $warehouseId = DB::table($table)
                    ->where('id', $mutation->reference_id)
                    ->value('warehouse_id');
                if (! $warehouseId) {
                    $ambiguous++;

                    return;
                }

                DB::table('stock_mutations')
                    ->where('id', $mutation->id)
                    ->update(['warehouse_id' => $warehouseId, 'updated_at' => now()]);
                $updated++;
            });

        $this->line("Stock mutations backfilled: {$updated}");
        $this->line("Stock mutations left ambiguous: {$ambiguous}");
    }
}
