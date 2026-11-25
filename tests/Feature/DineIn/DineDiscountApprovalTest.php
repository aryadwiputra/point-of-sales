<?php

namespace Tests\Feature\DineIn;

use App\Models\Category;
use App\Models\DineArea;
use App\Models\DineBill;
use App\Models\DineOrder;
use App\Models\DineSession;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use App\Services\DineBillService;
use App\Services\DineBillSettlementService;
use App\Services\DineSessionService;
use App\Support\Dine\DineSettlementContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DineDiscountApprovalTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private User $admin;

    private User $cashier;

    private Warehouse $warehouse;

    private Category $category;

    private DiningTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        $this->admin = User::where('email', 'arya@gmail.com')->first();
        $this->admin->markEmailAsVerified();
        $this->cashier = User::where('email', 'cashier@gmail.com')->first();
        $this->cashier->markEmailAsVerified();
        $this->warehouse = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $this->category = Category::create([
            'name' => 'Kategori Test',
            'image' => 'categories/test.jpg',
            'description' => 'Kategori untuk test',
        ]);
        $area = DineArea::create(['name' => 'Indoor', 'is_active' => true]);
        $this->table = DiningTable::create([
            'dine_area_id' => $area->id,
            'name' => 'Meja Approval',
            'is_active' => true,
            'capacity' => 4,
        ]);
        app(CashierShiftService::class)->openShift(
            $this->cashier,
            $this->cashier,
            0,
            null,
            $this->warehouse->id,
        );
        Setting::set('discount_approval_threshold', '1000');
    }

    private function pendingBill(): array
    {
        self::$seq++;
        $product = Product::create([
            'title' => 'Menu Approval '.self::$seq,
            'sku' => 'SKU-APR-'.self::$seq,
            'buy_price' => 4000,
            'sell_price' => 10000,
            'stock' => 10,
            'image' => 'products/test.jpg',
            'barcode' => 'BC-APR-'.self::$seq,
            'description' => 'Produk test',
            'tax_rate' => 0,
            'tax_type' => 'exclusive',
            'category_id' => $this->category->id,
        ]);
        $product->warehouses()->attach($this->warehouse->id, ['stock' => 10]);

        $session = app(DineSessionService::class)->open($this->table, 2, null, $this->cashier->id);
        $bill = DineBill::create([
            'dine_session_id' => $session->id,
            'sequence' => 1,
            'status' => DineBill::STATUS_OPEN,
            'subtotal' => 20000,
            'grand_total' => 20000,
        ]);
        $order = DineOrder::create([
            'dine_table_id' => $this->table->id,
            'dine_session_id' => $session->id,
            'access_token' => 'apr-'.uniqid(),
            'status' => DineOrder::STATUS_ACCEPTED,
            'source' => DineOrder::SOURCE_QR,
            'subtotal' => 20000,
        ]);
        $order->items()->create([
            'dine_bill_id' => $bill->id,
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 10000,
            'unit_cost' => 4000,
            'tax_type' => 'exclusive',
            'tax_rate' => 0,
        ]);

        $result = app(DineBillSettlementService::class)->settle($bill, new DineSettlementContext(
            userId: $this->cashier->id,
            manualDiscount: 2000,
            isCashPayment: true,
            cashAmount: 20000,
        ));

        return [$bill, $order, $result];
    }

    public function test_approve_settles_bill_and_closes_session(): void
    {
        [$bill, $order, $result] = $this->pendingBill();

        $this->actingAs($this->admin)
            ->post(route('discount-approvals.approve', $result->transaction))
            ->assertSessionHasNoErrors();

        $this->assertSame(DineBill::STATUS_SETTLED, $bill->fresh()->status);
        $this->assertNotNull($bill->fresh()->settled_at);
        $this->assertSame(DineOrder::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame(DineSession::STATUS_CLOSED, $bill->session->fresh()->status);
    }

    public function test_deny_reopens_bill_and_clears_transaction_link(): void
    {
        [$bill, $order, $result] = $this->pendingBill();

        $this->actingAs($this->admin)
            ->post(route('discount-approvals.deny', $result->transaction), ['notes' => 'Terlalu besar'])
            ->assertSessionHasNoErrors();

        $bill = $bill->fresh();
        $this->assertSame(DineBill::STATUS_OPEN, $bill->status);
        $this->assertNull($bill->transaction_id);
        $this->assertNull($bill->settled_at);
        $this->assertSame(0, (int) $bill->discount_total);
        $this->assertSame(20000, (int) $bill->subtotal);
        $this->assertSame(20000, (int) $bill->grand_total);
        $this->assertSame(DineOrder::STATUS_ACCEPTED, $order->fresh()->status);
    }

    public function test_split_and_void_reject_pending_approval_bill(): void
    {
        [$bill, $order] = $this->pendingBill();
        $item = $order->items()->first();
        $service = app(DineBillService::class);

        try {
            $service->split($bill, [$item->id], $this->cashier->id);
            $this->fail('Expected ValidationException for split on pending approval bill.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('bill', $exception->errors());
        }

        try {
            $service->voidItem($bill, $item, 'Salah', $this->cashier->id, $this->warehouse->id);
            $this->fail('Expected ValidationException for void on pending approval bill.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('bill', $exception->errors());
        }
    }
}
