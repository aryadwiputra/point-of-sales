<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditOutletCommand extends Command
{
    protected $signature = 'outlet:audit';

    protected $description = 'Report outlet, warehouse, assignment, legacy-location, and stock data without changing it';

    public function handle(): int
    {
        if (! Schema::hasTable('outlets') || ! Schema::hasTable('warehouses')) {
            $this->warn('Outlet schema is not installed. Run migrations first.');

            return self::SUCCESS;
        }

        $this->info('Outlet data audit (read-only)');
        $this->line('Outlets: '.DB::table('outlets')->count());
        $this->line('Active outlets: '.DB::table('outlets')->where('is_active', true)->count());
        $this->line('Sales-enabled outlets: '.DB::table('outlets')->where('is_sales_enabled', true)->count());
        $this->line('Warehouses: '.DB::table('warehouses')->count());
        $this->line('Warehouses without outlet: '.DB::table('warehouses')->whereNull('outlet_id')->count());
        if (Schema::hasTable('users') && Schema::hasTable('user_outlets')) {
            $this->line('Users without outlet assignment: '.DB::table('users')
                ->whereNotExists(fn ($query) => $query->selectRaw('1')
                    ->from('user_outlets')
                    ->whereColumn('user_outlets.user_id', 'users.id'))
                ->count());
        }
        if (Schema::hasTable('cashier_shifts')) {
            $this->line('Open shifts: '.DB::table('cashier_shifts')->where('status', 'open')->count());
        }

        $this->auditLegacyLocations();
        $this->auditStock();
        $this->auditSettings();
        $this->auditPusat();

        return self::SUCCESS;
    }

    private function auditLegacyLocations(): void
    {
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
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'warehouse_id')) {
                $this->line("{$table} without warehouse: ".DB::table($table)->whereNull('warehouse_id')->count());
            }
        }
    }

    private function auditStock(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('product_warehouse')) {
            return;
        }

        $mismatches = DB::table('products')
            ->leftJoin('product_warehouse', 'product_warehouse.product_id', '=', 'products.id')
            ->select('products.id')
            ->selectRaw('products.stock, COALESCE(SUM(product_warehouse.stock), 0) AS pivot_total')
            ->groupBy('products.id', 'products.stock')
            ->havingRaw('products.stock <> pivot_total')
            ->count();

        $this->line("Product stock mismatches: {$mismatches}");
    }

    private function auditSettings(): void
    {
        foreach (['settings', 'payment_settings', 'bank_accounts'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'outlet_id')) {
                continue;
            }

            $global = DB::table($table)->whereNull('outlet_id')->count();
            $scoped = DB::table($table)->whereNotNull('outlet_id')->count();
            $this->line("{$table}: {$global} global, {$scoped} outlet-scoped");
        }
    }

    private function auditPusat(): void
    {
        $pusat = DB::table('outlets')->where('code', 'PUSAT')->first();
        if (! $pusat) {
            $this->warn('PUSAT outlet not found.');

            return;
        }

        $this->line('PUSAT sales enabled: '.((int) $pusat->is_sales_enabled ? 'yes' : 'no'));
        $this->line('PUSAT warehouses: '.DB::table('warehouses')->where('outlet_id', $pusat->id)->count());
    }
}
