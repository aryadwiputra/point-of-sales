<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected array $tables = [
        'users',
        'outlets',
        'warehouses',
        'categories',
        'products',
        'units',
        'customers',
        'suppliers',
        'transactions',
        'transaction_details',
        'transaction_tenders',
        'carts',
        'profits',
        'cashier_shifts',
        'shift_cash_movements',
        'stock_mutations',
        'product_batches',
        'stock_transfers',
        'stock_transfer_items',
        'stock_opnames',
        'stock_opname_items',
        'purchase_orders',
        'purchase_order_items',
        'goods_receivings',
        'goods_receiving_items',
        'supplier_returns',
        'supplier_return_items',
        'sales_returns',
        'sales_return_items',
        'customer_credits',
        'receivables',
        'receivable_payments',
        'payables',
        'payable_payments',
        'pricing_rules',
        'price_lists',
        'dine_areas',
        'dine_tables',
        'dine_orders',
        'dine_order_items',
        'customer_segments',
        'customer_campaigns',
        'customer_vouchers',
        'bank_accounts',
        'payment_settings',
        'loyalty_point_histories',
        'discount_approval_logs',
        'audit_logs',
        'settings',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                });
            }
        }

        // Migrate existing records to default tenant if tenants table exists and has rows or needs default row
        if (Schema::hasTable('tenants')) {
            $defaultTenantId = DB::table('tenants')->where('subdomain', 'default')->orWhere('id', 1)->value('id');

            if (! $defaultTenantId && Schema::hasTable('plans')) {
                $planId = DB::table('plans')->where('slug', 'pro')->value('id');
                $defaultTenantId = DB::table('tenants')->insertGetId([
                    'name' => 'Berkah Jaya Niaga',
                    'subdomain' => 'default',
                    'plan_id' => $planId,
                    'status' => 'active',
                    'trial_ends_at' => now()->addDays(30),
                    'subscription_ends_at' => now()->addYear(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($defaultTenantId) {
                foreach ($this->tables as $tableName) {
                    if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                        DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->dropIndex([$tableName . '_tenant_id_index']);
                    $table->dropColumn('tenant_id');
                });
            }
        }
    }
};
