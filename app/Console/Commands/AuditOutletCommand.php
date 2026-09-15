<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditOutletCommand extends Command
{
    protected $signature = 'outlet:audit {--strict : Exit with failure when rollout invariants are not clean}';

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
        $warehousesWithoutOutlet = DB::table('warehouses')->whereNull('outlet_id')->count();
        $this->line('Warehouses without outlet: '.$warehousesWithoutOutlet);
        $issues = $warehousesWithoutOutlet > 0
            ? ["{$warehousesWithoutOutlet} warehouses without outlet"]
            : [];
        if (Schema::hasTable('users') && Schema::hasTable('user_outlets')) {
            $usersWithoutAssignment = DB::table('users')
                ->whereNotExists(fn ($query) => $query->selectRaw('1')
                    ->from('user_outlets')
                    ->whereColumn('user_outlets.user_id', 'users.id'))
                ->count();
            $this->line('Users without outlet assignment: '.$usersWithoutAssignment);
            if ($usersWithoutAssignment > 0) {
                $issues[] = "{$usersWithoutAssignment} users without outlet assignment";
            }
        }
        if (Schema::hasTable('cashier_shifts')) {
            $this->line('Open shifts: '.DB::table('cashier_shifts')->where('status', 'open')->count());
        }

        $issues = array_merge(
            $issues,
            $this->auditLegacyLocations(),
            $this->auditStock(),
            $this->auditSettings(),
            $this->auditPusat(),
        );

        if ($this->option('strict') && $issues !== []) {
            $this->error('Strict audit failed: '.implode('; ', $issues));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function auditLegacyLocations(): array
    {
        $issues = [];
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
                $count = DB::table($table)->whereNull('warehouse_id')->count();
                $this->line("{$table} without warehouse: {$count}");
                if ($count > 0) {
                    $issues[] = "{$table} has {$count} legacy rows";
                }
            }
        }

        return $issues;
    }

    private function auditStock(): array
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('product_warehouse')) {
            return [];
        }

        $mismatches = DB::table('products')
            ->leftJoin('product_warehouse', 'product_warehouse.product_id', '=', 'products.id')
            ->select('products.id')
            ->selectRaw('products.stock, COALESCE(SUM(product_warehouse.stock), 0) AS pivot_total')
            ->groupBy('products.id', 'products.stock')
            ->havingRaw('products.stock <> pivot_total')
            ->count();

        $this->line("Product stock mismatches: {$mismatches}");

        return $mismatches > 0 ? ["{$mismatches} product stock mismatches"] : [];
    }

    private function auditSettings(): array
    {
        $issues = [];
        foreach (['settings', 'payment_settings', 'bank_accounts'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'outlet_id')) {
                continue;
            }

            $global = DB::table($table)->whereNull('outlet_id')->count();
            $scoped = DB::table($table)->whereNotNull('outlet_id')->count();
            $this->line("{$table}: {$global} global, {$scoped} outlet-scoped");
        }

        return $issues;
    }

    private function auditPusat(): array
    {
        $pusat = DB::table('outlets')->where('code', 'PUSAT')->first();
        if (! $pusat) {
            $this->warn('PUSAT outlet not found.');

            return ['PUSAT outlet not found'];
        }

        $this->line('PUSAT sales enabled: '.((int) $pusat->is_sales_enabled ? 'yes' : 'no'));
        $this->line('PUSAT warehouses: '.DB::table('warehouses')->where('outlet_id', $pusat->id)->count());

        return $pusat->is_sales_enabled ? ['PUSAT is sales-enabled'] : [];
    }
}
