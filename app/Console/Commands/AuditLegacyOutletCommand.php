<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditLegacyOutletCommand extends Command
{
    protected $signature = 'outlet:legacy-audit';

    protected $description = 'Classify warehouse-less records without changing data';

    public function handle(): int
    {
        $this->info('Legacy outlet audit (read-only; no backfill performed)');

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
}
