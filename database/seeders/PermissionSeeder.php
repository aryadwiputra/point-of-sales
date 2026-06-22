<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $create = fn ($name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

        // dashboard permissions
        $create('dashboard-access');

        // users permissions
        $create('users-access');
        $create('users-create');
        $create('users-update');
        $create('users-delete');

        // roles permissions
        $create('roles-access');
        $create('roles-create');
        $create('roles-update');
        $create('roles-delete');

        // permissions permissions
        $create('permissions-access');
        $create('permissions-create');
        $create('permissions-update');
        $create('permissions-delete');

        // permission categories
        $create('categories-access');
        $create('categories-create');
        $create('categories-edit');
        $create('categories-delete');

        // permission products
        $create('products-access');
        $create('products-create');
        $create('products-edit');
        $create('products-delete');
        $create('pricing-rules-access');
        $create('pricing-rules-create');
        $create('pricing-rules-update');
        $create('pricing-rules-delete');

        // permission customers
        $create('customers-access');
        $create('customers-create');
        $create('customers-edit');
        $create('customers-delete');
        $create('customer-vouchers-access');
        $create('customer-vouchers-create');
        $create('customer-vouchers-update');
        $create('customer-vouchers-delete');
        $create('customer-segments-access');
        $create('customer-segments-create');
        $create('customer-segments-update');
        $create('customer-segments-delete');
        $create('crm-campaigns-access');
        $create('crm-campaigns-create');
        $create('crm-campaigns-update');
        $create('crm-campaigns-delete');
        $create('crm-reminders-access');

        // permission transactions
        $create('transactions-access');
        $create('transactions-confirm-payment');

        // permission receivables & payables
        $create('receivables-access');
        $create('receivables-pay');
        $create('payables-access');
        $create('payables-pay');
        $create('suppliers-access');

        // permission reports
        $create('reports-access');
        $create('profits-access');

        // payment settings
        $create('payment-settings-access');
        $create('payment-settings-update');

        // stock opnames
        $create('stock-opnames-access');
        $create('stock-opnames-create');
        $create('stock-opnames-finalize');
        $create('stock-mutations-access');

        // sales returns
        $create('sales-returns-access');
        $create('sales-returns-create');
        $create('sales-returns-complete');

        // cashier shifts
        $create('cashier-shifts-access');
        $create('cashier-shifts-open');
        $create('cashier-shifts-close');
        $create('cashier-shifts-force-close');

        // audit logs
        $create('audit-logs-access');

        // purchase orders
        $create('purchase-orders-access');
        $create('purchase-orders-create');
        $create('purchase-orders-update');
        $create('purchase-orders-delete');

        // goods receivings
        $create('goods-receivings-access');
        $create('goods-receivings-create');

        // supplier returns
        $create('supplier-returns-access');
        $create('supplier-returns-create');
        $create('supplier-returns-update');

        // stock transfers
        $create('stock-transfers-access');
        $create('stock-transfers-create');
        $create('stock-transfers-send');
        $create('stock-transfers-receive');
        $create('stock-transfers-cancel');

        // import/export
        $create('products-import');
        $create('products-export');
        $create('customers-import');
        $create('customers-export');

        // warehouses
        $create('warehouses-access');
        $create('warehouses-create');
        $create('warehouses-update');
        $create('warehouses-delete');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
