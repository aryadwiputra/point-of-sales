<?php

namespace Tests\Feature\DineIn;

use App\Models\Cart;
use App\Models\Category;
use App\Models\DineArea;
use App\Models\DineBill;
use App\Models\DineOrder;
use App\Models\DineSession;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use App\Services\DineSessionService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DineBillRoutesTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Warehouse $warehouse;

    private DiningTable $table;

    private DineSession $session;

    private DineBill $bill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        $this->cashier = User::where('email', 'cashier@gmail.com')->first();
        $this->cashier->markEmailAsVerified();
        $this->warehouse = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $category = Category::create([
            'name' => 'Kategori Test',
            'image' => 'categories/test.jpg',
            'description' => 'Kategori untuk test',
        ]);
        $area = DineArea::create(['name' => 'Indoor', 'is_active' => true]);
        $this->table = DiningTable::create([
            'dine_area_id' => $area->id,
            'name' => 'Meja Bill Route',
            'is_active' => true,
            'capacity' => 4,
        ]);
        $this->session = app(DineSessionService::class)->open($this->table, 2, null, $this->cashier->id);
        $this->bill = DineBill::create([
            'dine_session_id' => $this->session->id,
            'sequence' => 1,
            'status' => DineBill::STATUS_OPEN,
        ]);
        app(CashierShiftService::class)->openShift(
            $this->cashier,
            $this->cashier,
            0,
            null,
            $this->warehouse->id,
        );
        Product::create([
            'title' => 'Produk Bill Route',
            'sku' => 'SKU-ROUTE-'.uniqid(),
            'buy_price' => 4000,
            'sell_price' => 10000,
            'stock' => 10,
            'image' => 'products/test.jpg',
            'barcode' => 'BC-ROUTE-'.uniqid(),
            'description' => 'Produk test',
            'tax_rate' => 0,
            'category_id' => $category->id,
        ]);
    }

    public function test_split_and_settle_routes(): void
    {
        $order = DineOrder::create([
            'dine_table_id' => $this->table->id,
            'dine_session_id' => $this->session->id,
            'access_token' => 'route-'.uniqid(),
            'status' => DineOrder::STATUS_ACCEPTED,
            'source' => DineOrder::SOURCE_STAFF,
            'subtotal' => 20000,
        ]);
        $item = $order->items()->create([
            'dine_bill_id' => $this->bill->id,
            'product_id' => Product::first()->id,
            'qty' => 2,
            'price' => 10000,
            'unit_cost' => 4000,
            'tax_type' => 'exclusive',
            'tax_rate' => 0,
        ]);

        $this->actingAs($this->cashier)
            ->post(route('dine-bills.split', $this->bill), ['item_ids' => [$item->id]])
            ->assertSessionHasNoErrors();

        $newBill = DineBill::where('id', '!=', $this->bill->id)->firstOrFail();

        $this->actingAs($this->cashier)
            ->post(route('dine-bills.settle', $newBill), [
                'cash' => 20000,
                'discount' => 0,
                'redeem_points' => 0,
                'pay_later' => false,
            ])
            ->assertRedirect(route('transactions.print', Transaction::firstOrFail()->invoice));
    }

    public function test_submit_cart_and_void_item_routes(): void
    {
        Cart::create([
            'cashier_id' => $this->cashier->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => Product::first()->id,
            'qty' => 1,
            'price' => 10000,
            'conversion_factor' => 1,
        ]);

        $this->actingAs($this->cashier)
            ->post(route('dine-bills.submit-cart', $this->bill))
            ->assertSessionHasNoErrors();

        $item = $this->bill->items()->firstOrFail();

        $this->actingAs($this->cashier)
            ->delete(route('dine-bills.void-item', [$this->bill, $item]), ['reason' => 'Salah input'])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($item->fresh()->voided_at);
    }

    public function test_user_without_process_permission_gets_403(): void
    {
        $user = User::factory()->create();
        $user->markEmailAsVerified();

        $this->actingAs($user)
            ->post(route('dine-bills.split', $this->bill), ['item_ids' => [1]])
            ->assertForbidden();
    }
}
