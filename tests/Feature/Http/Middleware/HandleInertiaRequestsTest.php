<?php

namespace Tests\Feature\Http\Middleware;

use App\Models\Customer;
use App\Models\DineArea;
use App\Models\DineOrder;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\Payable;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\Receivable;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HandleInertiaRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'discounts-approve',
            'dine-orders-access',
        ] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }

    private function makeOutletWithWarehouse(string $code): array
    {
        $outlet = Outlet::create([
            'code' => $code,
            'name' => 'Outlet '.$code,
        ]);

        $warehouse = Warehouse::create([
            'code' => 'WH-'.$code,
            'name' => 'Gudang '.$code,
            'type' => 'branch',
            'is_active' => true,
            'sort_order' => 0,
            'outlet_id' => $outlet->id,
        ]);

        return [$outlet, $warehouse];
    }

    private function makeUserForOutlet(Outlet $outlet, array $permissions = []): User
    {
        $user = User::factory()->create();
        $user->markEmailAsVerified();
        $user->outlets()->attach($outlet->id, ['is_default' => true]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    private function makeTransaction(Outlet $outlet, Warehouse $warehouse, string $invoice, array $overrides = []): Transaction
    {
        $cashier = User::factory()->create();

        return Transaction::create(array_merge([
            'invoice' => $invoice,
            'cashier_id' => $cashier->id,
            'user_id' => $cashier->id,
            'warehouse_id' => $warehouse->id,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'discount_approval_status' => 'pending',
            'grand_total' => 10000,
            'total' => 10000,
            'discount' => 5000,
            'cash' => 0,
            'change' => 0,
            'access_token' => Str::uuid()->toString(),
        ], $overrides));
    }

    public function test_pending_approval_count_is_scoped_to_accessible_outlets(): void
    {
        [$outletA, $warehouseA] = $this->makeOutletWithWarehouse('APR');
        [$outletB, $warehouseB] = $this->makeOutletWithWarehouse('BPR');

        $this->makeTransaction($outletA, $warehouseA, 'INV-APR-1');
        $this->makeTransaction($outletB, $warehouseB, 'INV-BPR-1');

        $user = $this->makeUserForOutlet($outletA, ['discounts-approve']);

        $response = $this->actingAs($user)->get('/dashboard/access');
        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->where('pendingApprovalCount', 1));
    }

    public function test_pending_dine_orders_count_is_scoped_to_accessible_outlets(): void
    {
        [$outletA] = $this->makeOutletWithWarehouse('DIN');
        [$outletB] = $this->makeOutletWithWarehouse('DJN');

        $areaA = DineArea::create(['outlet_id' => $outletA->id, 'name' => 'Area A']);
        $areaB = DineArea::create(['outlet_id' => $outletB->id, 'name' => 'Area B']);
        $tableA = DiningTable::create(['dine_area_id' => $areaA->id, 'name' => 'T-A1']);
        $tableB = DiningTable::create(['dine_area_id' => $areaB->id, 'name' => 'T-B1']);

        DineOrder::create(['dine_table_id' => $tableA->id, 'status' => 'submitted']);
        DineOrder::create(['dine_table_id' => $tableB->id, 'status' => 'submitted']);

        $user = $this->makeUserForOutlet($outletA, ['dine-orders-access']);

        $response = $this->actingAs($user)->get('/dashboard/access');
        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->where('pendingDineOrdersCount', 1));
    }

    public function test_expiring_batch_notifications_are_scoped_to_accessible_warehouses(): void
    {
        [$outletA, $warehouseA] = $this->makeOutletWithWarehouse('BAT');
        [, $warehouseB] = $this->makeOutletWithWarehouse('BBT');

        $category = \App\Models\Category::create(['name' => 'Kat', 'image' => '', 'description' => '']);
        $product = Product::create([
            'title' => 'Produk Batch',
            'barcode' => 'BATCH-1',
            'sku' => 'SKU-BATCH-1',
            'image' => '',
            'description' => '',
            'buy_price' => 1000,
            'sell_price' => 2000,
            'stock' => 20,
            'category_id' => $category->id,
            'tax_rate' => 0,
            'tax_type' => 'exclusive',
            'min_stock' => 0,
            'max_stock' => 100,
            'is_composite' => false,
        ]);

        ProductBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseA->id,
            'batch_number' => 'B-A',
            'expired_at' => now()->addDays(5),
            'received_at' => now()->subDays(5),
            'stock' => 10,
        ]);
        ProductBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseB->id,
            'batch_number' => 'B-B',
            'expired_at' => now()->addDays(6),
            'received_at' => now()->subDays(5),
            'stock' => 10,
        ]);

        $user = $this->makeUserForOutlet($outletA);

        $response = $this->actingAs($user)->get('/dashboard/access');
        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->has('expiringBatchNotifications', 1)
            ->where('expiringBatchNotifications.0.batch_number', 'B-A'));
    }

    public function test_receivable_notifications_are_scoped_to_accessible_outlets(): void
    {
        [$outletA, $warehouseA] = $this->makeOutletWithWarehouse('RCV');
        [$outletB, $warehouseB] = $this->makeOutletWithWarehouse('BRV');

        $customer = Customer::create(['name' => 'Cust RCV', 'no_telp' => '628111', 'address' => 'Jl. RCV']);

        $this->makeTransaction($outletA, $warehouseA, 'INV-RCV-1', [
            'discount_approval_status' => null,
            'payment_method' => 'pay_later',
            'payment_status' => 'unpaid',
        ]);

        $transactionA = Transaction::where('invoice', 'INV-RCV-1')->first();
        Receivable::create([
            'transaction_id' => $transactionA->id,
            'customer_id' => $customer->id,
            'invoice' => 'RCV-A',
            'due_date' => now()->addDay(),
            'total' => 10000,
            'paid' => 0,
            'status' => 'partial',
        ]);

        $this->makeTransaction($outletB, $warehouseB, 'INV-RCV-2', [
            'discount_approval_status' => null,
            'payment_method' => 'pay_later',
            'payment_status' => 'unpaid',
        ]);

        $transactionB = Transaction::where('invoice', 'INV-RCV-2')->first();
        Receivable::create([
            'transaction_id' => $transactionB->id,
            'customer_id' => $customer->id,
            'invoice' => 'RCV-B',
            'due_date' => now()->addDay(),
            'total' => 20000,
            'paid' => 0,
            'status' => 'partial',
        ]);

        $user = $this->makeUserForOutlet($outletA);

        $response = $this->actingAs($user)->get('/dashboard/access');
        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->has('receivableNotifications', 1)
            ->where('receivableNotifications.0.title', 'Piutang: RCV-A')
            ->where('receivableAgingSummary', fn ($summary) => collect($summary)
                ->contains(fn ($bucket) => $bucket['bucket'] === 'current'
                    && $bucket['count'] === 1
                    && $bucket['total'] === 10000)));
    }

    public function test_payable_notifications_are_scoped_to_accessible_outlets(): void
    {
        [$outletA, $warehouseA] = $this->makeOutletWithWarehouse('PAY');
        [$outletB, $warehouseB] = $this->makeOutletWithWarehouse('BAY');

        $supplier = Supplier::create([
            'name' => 'Supplier PAY',
            'phone' => '628112233',
            'address' => 'Jl. Supplier',
        ]);

        foreach ([
            [$warehouseA, 'PO-PAY-A'],
            [$warehouseB, 'PO-PAY-B'],
        ] as [$warehouse, $doc]) {
            $po = PurchaseOrder::create([
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $supplier->id,
                'document_number' => $doc,
                'status' => 'completed',
                'order_date' => now()->subDays(10),
                'expected_date' => now()->subDays(5),
            ]);

            Payable::create([
                'purchase_order_id' => $po->id,
                'supplier_id' => $supplier->id,
                'document_number' => $doc,
                'due_date' => now()->addDay(),
                'total' => 50000,
                'paid' => 0,
                'status' => 'partial',
            ]);
        }

        $user = $this->makeUserForOutlet($outletA);

        $response = $this->actingAs($user)->get('/dashboard/access');
        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->has('payableNotifications', 1)
            ->where('payableNotifications.0.title', 'Hutang: PO-PAY-A')
            ->where('payableAgingSummary', fn ($summary) => collect($summary)
                ->contains(fn ($bucket) => $bucket['bucket'] === 'current'
                    && $bucket['count'] === 1
                    && $bucket['total'] === 50000)));
    }

    public function test_superadmin_sees_all_outlet_data(): void
    {
        [$outletA, $warehouseA] = $this->makeOutletWithWarehouse('SAD');
        [$outletB, $warehouseB] = $this->makeOutletWithWarehouse('SBD');

        $this->makeTransaction($outletA, $warehouseA, 'INV-SAD-1');
        $this->makeTransaction($outletB, $warehouseB, 'INV-SBD-1');

        $user = User::factory()->create();
        $user->markEmailAsVerified();
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get('/dashboard/access');
        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->where('pendingApprovalCount', 2));
    }
}
