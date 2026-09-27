<?php

namespace Tests\Feature\CashierShifts;

use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\Transaction;
use App\Models\TransactionTender;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CashierShiftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'transactions-access',
            'cashier-shifts-access',
            'cashier-shifts-open',
            'cashier-shifts-close',
            'cashier-shifts-force-close',
        ] as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_cashier_can_open_shift(): void
    {
        $cashier = $this->createUserWithPermissions([
            'cashier-shifts-access',
            'cashier-shifts-open',
        ]);

        $response = $this
            ->actingAs($cashier)
            ->post(route('cashier-shifts.store'), [
                'opening_cash' => 150000,
                'notes' => 'Modal pagi',
            ]);

        $shift = CashierShift::first();

        $response->assertRedirect(route('cashier-shifts.show', $shift));
        $this->assertNotNull($shift);
        $this->assertSame($cashier->id, $shift->user_id);
        $this->assertSame(150000, (int) $shift->opening_cash);
        $this->assertSame(CashierShift::STATUS_OPEN, $shift->status);
    }

    public function test_cashier_cannot_open_second_shift_while_first_is_active(): void
    {
        $cashier = $this->createUserWithPermissions([
            'cashier-shifts-access',
            'cashier-shifts-open',
        ]);

        CashierShift::create([
            'user_id' => $cashier->id,
            'opened_by' => $cashier->id,
            'opened_at' => now(),
            'opening_cash' => 100000,
            'expected_cash' => 100000,
            'status' => CashierShift::STATUS_OPEN,
        ]);

        $response = $this
            ->from(route('cashier-shifts.index'))
            ->actingAs($cashier)
            ->post(route('cashier-shifts.store'), [
                'opening_cash' => 200000,
            ]);

        $response->assertInvalid(['opening_cash']);
        $this->assertDatabaseCount('cashier_shifts', 1);
    }

    public function test_transaction_page_receives_active_shift_shared_prop(): void
    {
        $cashier = $this->createUserWithPermissions([
            'transactions-access',
            'cashier-shifts-access',
        ]);

        $shift = CashierShift::create([
            'user_id' => $cashier->id,
            'opened_by' => $cashier->id,
            'opened_at' => now(),
            'opening_cash' => 90000,
            'expected_cash' => 90000,
            'status' => CashierShift::STATUS_OPEN,
        ]);

        $response = $this
            ->actingAs($cashier)
            ->get(route('transactions.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Transactions/Index')
            ->where('activeCashierShift.id', $shift->id)
            ->where('activeCashierShift.opening_cash', 90000));
    }

    public function test_closing_shift_calculates_expected_cash_and_difference(): void
    {
        $cashier = $this->createUserWithPermissions([
            'cashier-shifts-access',
            'cashier-shifts-close',
        ]);

        $shift = CashierShift::create([
            'user_id' => $cashier->id,
            'opened_by' => $cashier->id,
            'opened_at' => now(),
            'opening_cash' => 100000,
            'expected_cash' => 100000,
            'status' => CashierShift::STATUS_OPEN,
        ]);

        $customer = Customer::create([
            'name' => 'Customer Shift',
            'no_telp' => '0812000000',
            'address' => 'Alamat Shift',
        ]);

        $category = Category::create([
            'name' => 'Shift',
            'description' => 'Shift',
            'image' => 'shift.png',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'image' => 'shift-product.png',
            'barcode' => 'BRCD-'.Str::upper(Str::random(8)),
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'title' => 'Produk Shift',
            'description' => 'Produk Shift',
            'buy_price' => 40000,
            'sell_price' => 60000,
            'stock' => 10,
        ]);

        Transaction::create([
            'cashier_id' => $cashier->id,
            'cashier_shift_id' => $shift->id,
            'customer_id' => $customer->id,
            'invoice' => 'TRX-'.Str::upper(Str::random(8)),
            'cash' => 60000,
            'change' => 0,
            'discount' => 0,
            'shipping_cost' => 0,
            'grand_total' => 60000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ])->details()->create([
            'product_id' => $product->id,
            'qty' => 1,
            'price' => 60000,
        ]);

        Transaction::create([
            'cashier_id' => $cashier->id,
            'cashier_shift_id' => $shift->id,
            'customer_id' => $customer->id,
            'invoice' => 'TRX-'.Str::upper(Str::random(8)),
            'cash' => 0,
            'change' => 0,
            'discount' => 0,
            'shipping_cost' => 0,
            'grand_total' => 50000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'pending',
        ]);

        SalesReturn::create([
            'code' => 'SR-'.Str::upper(Str::random(6)),
            'transaction_id' => Transaction::first()->id,
            'customer_id' => $customer->id,
            'cashier_id' => $cashier->id,
            'cashier_shift_id' => $shift->id,
            'status' => 'completed',
            'return_type' => 'refund_cash',
            'refund_amount' => 10000,
            'credited_amount' => 0,
            'total_return_amount' => 10000,
            'completed_at' => now(),
        ]);

        $response = $this
            ->actingAs($cashier)
            ->post(route('cashier-shifts.close', $shift), [
                'actual_cash' => 155000,
                'close_notes' => 'Cash count final',
            ]);

        $response->assertRedirect(route('cashier-shifts.show', $shift));
        $this->assertDatabaseHas('cashier_shifts', [
            'id' => $shift->id,
            'status' => CashierShift::STATUS_CLOSED,
            'expected_cash' => 150000,
            'actual_cash' => 155000,
            'cash_difference' => 5000,
            'cash_sales_total' => 60000,
            'non_cash_sales_total' => 50000,
            'cash_refund_total' => 10000,
        ]);
    }

    public function test_split_tenders_count_only_cash_tender_in_expected_cash(): void
    {
        $cashier = $this->createUserWithPermissions(['cashier-shifts-access']);
        $shift = CashierShift::create([
            'user_id' => $cashier->id,
            'opened_by' => $cashier->id,
            'opened_at' => now(),
            'opening_cash' => 100000,
            'expected_cash' => 100000,
            'status' => CashierShift::STATUS_OPEN,
        ]);
        $transaction = Transaction::create([
            'cashier_id' => $cashier->id,
            'cashier_shift_id' => $shift->id,
            'invoice' => 'TRX-SPLIT-SHIFT',
            'cash' => 50000,
            'change' => 0,
            'discount' => 0,
            'shipping_cost' => 0,
            'grand_total' => 75000,
            'payment_method' => 'split',
            'payment_status' => 'paid',
        ]);
        $transaction->tenders()->createMany([
            ['method' => TransactionTender::METHOD_CASH, 'amount' => 50000, 'cash_received' => 50000, 'payment_status' => 'paid'],
            ['method' => TransactionTender::METHOD_BANK_TRANSFER, 'amount' => 25000, 'payment_status' => 'paid'],
        ]);

        $summary = app(CashierShiftService::class)->calculateSummary($shift);

        $this->assertSame(50000, $summary['cash_sales_total']);
        $this->assertSame(25000, $summary['non_cash_sales_total']);
        $this->assertSame(150000, $summary['expected_cash']);
    }

    public function test_admin_with_force_close_permission_can_close_other_cashier_shift(): void
    {
        $cashier = $this->createUserWithPermissions([
            'cashier-shifts-access',
        ]);
        $admin = $this->createUserWithPermissions([
            'cashier-shifts-access',
            'cashier-shifts-close',
            'cashier-shifts-force-close',
        ]);

        $shift = CashierShift::create([
            'user_id' => $cashier->id,
            'opened_by' => $cashier->id,
            'opened_at' => now(),
            'opening_cash' => 80000,
            'expected_cash' => 80000,
            'status' => CashierShift::STATUS_OPEN,
        ]);

        $response = $this
            ->withSession($this->recentlyConfirmedSession())
            ->actingAs($admin)
            ->post(route('cashier-shifts.close', $shift), [
                'actual_cash' => 80000,
            ]);

        $response->assertRedirect(route('cashier-shifts.show', $shift));
        $this->assertDatabaseHas('cashier_shifts', [
            'id' => $shift->id,
            'closed_by' => $admin->id,
            'status' => CashierShift::STATUS_FORCE_CLOSED,
        ]);
    }

    public function test_force_close_redirects_to_confirm_password_when_confirmation_is_stale(): void
    {
        $cashier = $this->createUserWithPermissions(['cashier-shifts-access']);
        $admin = $this->createUserWithPermissions([
            'cashier-shifts-access',
            'cashier-shifts-close',
            'cashier-shifts-force-close',
        ]);

        $shift = CashierShift::create([
            'user_id' => $cashier->id,
            'opened_by' => $cashier->id,
            'opened_at' => now(),
            'opening_cash' => 80000,
            'expected_cash' => 80000,
            'status' => CashierShift::STATUS_OPEN,
        ]);

        $this->actingAs($admin)
            ->from(route('cashier-shifts.show', $shift))
            ->post(route('cashier-shifts.close', $shift), [
                'actual_cash' => 80000,
            ])
            ->assertRedirect(route('password.confirm'));
    }

    public function test_open_shift_without_warehouse_falls_back_to_sales_warehouse(): void
    {
        $outletPusat = Outlet::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'is_active' => true,
            'is_sales_enabled' => false,
        ]);
        $outletMal = Outlet::create([
            'code' => 'MAL',
            'name' => 'Outlet Malabar',
            'is_active' => true,
            'is_sales_enabled' => true,
        ]);

        $pusat = Warehouse::create([
            'outlet_id' => $outletPusat->id,
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $mal = Warehouse::create([
            'outlet_id' => $outletMal->id,
            'code' => 'WH-MAL',
            'name' => 'Gudang Malabar',
            'type' => 'branch',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $cashier = $this->createUserWithPermissions([
            'cashier-shifts-access',
            'cashier-shifts-open',
        ]);
        $cashier->outlets()->attach($outletMal->id, ['is_default' => true]);

        $response = $this
            ->actingAs($cashier)
            ->post(route('cashier-shifts.store'), [
                'opening_cash' => 100000,
                'redirect_to' => 'transactions',
            ]);

        $shift = CashierShift::first();

        $response->assertRedirect(route('transactions.index'));
        $this->assertNotNull($shift);
        $this->assertSame($mal->id, $shift->warehouse_id);
        $this->assertNotSame($pusat->id, $shift->warehouse_id);
    }

    public function test_transaction_page_receives_sales_warehouses_prop(): void
    {
        $outletPusat = Outlet::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'is_active' => true,
            'is_sales_enabled' => false,
        ]);
        $outletMal = Outlet::create([
            'code' => 'MAL',
            'name' => 'Outlet Malabar',
            'is_active' => true,
            'is_sales_enabled' => true,
        ]);

        Warehouse::create([
            'outlet_id' => $outletPusat->id,
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $mal = Warehouse::create([
            'outlet_id' => $outletMal->id,
            'code' => 'WH-MAL',
            'name' => 'Gudang Malabar',
            'type' => 'branch',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $cashier = $this->createUserWithPermissions([
            'transactions-access',
            'cashier-shifts-access',
        ]);
        $cashier->outlets()->attach($outletMal->id, ['is_default' => true]);

        $this->actingAs($cashier)
            ->get(route('transactions.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Transactions/Index')
                ->has('warehouses', 1)
                ->where('warehouses.0.id', $mal->id)
                ->where('warehouses.0.code', 'WH-MAL'));
    }

    private function createUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}
