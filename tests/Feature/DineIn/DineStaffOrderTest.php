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
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use App\Services\DineSessionService;
use App\Services\DineStaffOrderService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DineStaffOrderTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private User $cashier;

    private Warehouse $warehouse;

    private Category $category;

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
        $this->category = Category::create([
            'name' => 'Kategori Test',
            'image' => 'categories/test.jpg',
            'description' => 'Kategori untuk test',
        ]);
        $area = DineArea::create(['name' => 'Indoor', 'is_active' => true]);
        $this->table = DiningTable::create([
            'dine_area_id' => $area->id,
            'name' => 'Meja Staff',
            'is_active' => true,
            'capacity' => 4,
        ]);
        $this->session = app(DineSessionService::class)->open($this->table, 2, null, $this->cashier->id);
        $this->bill = DineBill::create([
            'dine_session_id' => $this->session->id,
            'sequence' => 1,
            'status' => DineBill::STATUS_OPEN,
            'created_by' => $this->cashier->id,
        ]);
        app(CashierShiftService::class)->openShift(
            $this->cashier,
            $this->cashier,
            0,
            null,
            $this->warehouse->id,
        );
    }

    private function createProduct(int $stock = 20): Product
    {
        self::$seq++;
        $product = Product::create([
            'title' => 'Menu Staff '.self::$seq,
            'sku' => 'SKU-STAFF-'.self::$seq,
            'buy_price' => 4000,
            'sell_price' => 10000,
            'stock' => $stock,
            'image' => 'products/test.jpg',
            'barcode' => 'BC-STAFF-'.self::$seq,
            'description' => 'Produk test',
            'tax_rate' => 0,
            'tax_type' => 'exclusive',
            'category_id' => $this->category->id,
        ]);
        $product->warehouses()->attach($this->warehouse->id, ['stock' => $stock]);

        return $product;
    }

    public function test_submit_cart_creates_accepted_staff_round_and_clears_carts(): void
    {
        $product = $this->createProduct(20);
        Cart::create([
            'cashier_id' => $this->cashier->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'qty' => 3,
            'price' => 30000,
            'conversion_factor' => 1,
        ]);

        $order = app(DineStaffOrderService::class)->submit($this->cashier->id, $this->bill);

        $this->assertSame(DineOrder::STATUS_ACCEPTED, $order->status);
        $this->assertSame(DineOrder::SOURCE_STAFF, $order->source);
        $this->assertSame($this->session->id, $order->dine_session_id);
        $this->assertSame($this->bill->id, $order->items()->first()->dine_bill_id);
        $this->assertSame(30000, $this->bill->fresh()->subtotal);
        $this->assertSame(17, $product->fresh()->stock);
        $this->assertSame(0, Cart::where('cashier_id', $this->cashier->id)->active()->count());
    }
}
