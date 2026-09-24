<?php

namespace Tests\Feature\DineIn;

use App\Models\Category;
use App\Models\DineArea;
use App\Models\DineOrder;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DineOrderTest extends TestCase
{
    use RefreshDatabase;

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

        $this->table = DiningTable::create([
            'name' => 'Meja 1',
            'is_active' => true,
            'capacity' => 4,
            'sort_order' => 0,
        ]);
    }

    private static int $seq = 0;

    private function createProduct(int $stock, bool $isComposite = false): Product
    {
        self::$seq++;

        $product = Product::create([
            'title' => 'Produk '.self::$seq,
            'sku' => 'SKU-DINE-'.self::$seq,
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => $stock,
            'image' => 'products/test.jpg',
            'barcode' => 'BC-DINE-'.self::$seq,
            'description' => 'Deskripsi produk test',
            'tax_rate' => 0,
            'category_id' => $this->category->id,
            'is_composite' => $isComposite,
        ]);

        $product->warehouses()->attach($this->warehouse->id, ['stock' => $stock]);

        return $product;
    }

    private function orderPayload(array $items, array $overrides = []): array
    {
        return array_merge([
            'items' => $items,
            'payment_option' => 'pay_at_counter',
        ], $overrides);
    }

    public function test_store_rejects_pay_online(): void
    {
        $product = $this->createProduct(10);

        $this->from(route('dine.menu', $this->table->token))
            ->post(route('dine-order.store', $this->table->token), $this->orderPayload(
                [['product_id' => $product->id, 'qty' => 1]],
                ['payment_option' => 'pay_online']
            ))
            ->assertSessionHasErrors('payment_option');

        $this->assertEquals(0, DineOrder::count());
    }

    public function test_store_pay_at_counter_succeeds(): void
    {
        $product = $this->createProduct(10);

        $this->post(route('dine-order.store', $this->table->token), $this->orderPayload([
            ['product_id' => $product->id, 'qty' => 2],
        ]))->assertSessionHasNoErrors();

        $order = DineOrder::firstOrFail();
        $this->assertEquals('submitted', $order->status);
        $this->assertEquals('pay_at_counter', $order->payment_option);
        $this->assertEquals(20000, $order->subtotal);
        $this->assertEquals(2, $order->item_count);
        $this->assertDatabaseHas('dine_order_items', [
            'dine_order_id' => $order->id,
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 10000,
        ]);
    }

    public function test_store_rejects_qty_exceeding_global_stock(): void
    {
        $product = $this->createProduct(3);

        $this->from(route('dine.menu', $this->table->token))
            ->post(route('dine-order.store', $this->table->token), $this->orderPayload([
                ['product_id' => $product->id, 'qty' => 5],
            ]))
            ->assertSessionHas('error');

        $this->assertEquals(0, DineOrder::count());
    }

    public function test_accept_decrements_warehouse_and_global_stock(): void
    {
        $product = $this->createProduct(50);
        $this->actingAs($this->cashier);
        app(CashierShiftService::class)->openShift($this->cashier, $this->cashier, 0, null, $this->warehouse->id);

        $this->post(route('dine-order.store', $this->table->token), $this->orderPayload([
            ['product_id' => $product->id, 'qty' => 3],
        ]));

        $order = DineOrder::firstOrFail();
        $this->actingAs($this->cashier);
        $this->post(route('dine-orders.accept', $order))->assertSessionHasNoErrors();

        $product = $product->fresh();
        $this->assertEquals(47, $product->stock);

        $pivot = ProductWarehouse::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(47, $pivot->stock);
        $this->assertEquals('accepted', $order->fresh()->status);
    }

    public function test_accept_records_transaction_profit_and_stock_mutation(): void
    {
        $product = $this->createProduct(50);
        $this->actingAs($this->cashier);
        app(CashierShiftService::class)->openShift($this->cashier, $this->cashier, 0, null, $this->warehouse->id);

        $this->post(route('dine-order.store', $this->table->token), $this->orderPayload([
            ['product_id' => $product->id, 'qty' => 3],
        ]));

        $order = DineOrder::firstOrFail();
        $this->actingAs($this->cashier);
        $this->post(route('dine-orders.accept', $order))->assertSessionHasNoErrors();

        $order = $order->fresh();
        $this->assertNotNull($order->transaction_id);

        $transaction = Transaction::findOrFail($order->transaction_id);
        $this->assertEquals(30000, $transaction->grand_total);
        $this->assertEquals('unpaid', $transaction->payment_status);
        $this->assertEquals('in_store', $transaction->order_type);
        $this->assertEquals($this->warehouse->id, $transaction->warehouse_id);

        $this->assertDatabaseHas('transaction_details', [
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'qty' => 3,
            'conversion_factor' => 1,
        ]);
        $this->assertEquals(15000, (int) $transaction->profits()->sum('total'));

        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'reference_type' => 'dine_order',
            'reference_id' => $order->id,
            'mutation_type' => 'out',
            'qty' => 3,
            'stock_before' => 50,
            'stock_after' => 47,
        ]);
    }

    public function test_accept_decrements_base_units_for_multi_unit_product(): void
    {
        $product = $this->createProduct(50);
        $dozen = Unit::create(['code' => 'DUS-DINE', 'name' => 'Dus', 'symbol' => 'dus']);
        $product->units()->attach($dozen->id, [
            'is_base' => false,
            'conversion_factor' => 12,
            'buy_price' => 60000,
            'sell_price' => 120000,
        ]);

        $this->actingAs($this->cashier);
        app(CashierShiftService::class)->openShift($this->cashier, $this->cashier, 0, null, $this->warehouse->id);

        $this->post(route('dine-order.store', $this->table->token), $this->orderPayload([
            ['product_id' => $product->id, 'unit_id' => $dozen->id, 'qty' => 1],
        ]))->assertSessionHasNoErrors();

        $order = DineOrder::firstOrFail();
        $this->assertEquals(12, (float) $order->items()->first()->conversion_factor);

        $this->actingAs($this->cashier);
        $this->post(route('dine-orders.accept', $order))->assertSessionHasNoErrors();

        $this->assertEquals(38, $product->fresh()->stock);
        $this->assertEquals(38, ProductWarehouse::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first()->stock);

        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'reference_type' => 'dine_order',
            'reference_id' => $order->id,
            'qty' => 12,
            'stock_before' => 50,
            'stock_after' => 38,
        ]);

        $transaction = Transaction::findOrFail($order->fresh()->transaction_id);
        $this->assertEquals(12, (int) $transaction->details()->first()->conversion_factor);
    }

    public function test_accept_rejects_insufficient_stock_atomically(): void
    {
        $product = $this->createProduct(50);
        $this->actingAs($this->cashier);
        app(CashierShiftService::class)->openShift($this->cashier, $this->cashier, 0, null, $this->warehouse->id);

        $this->post(route('dine-order.store', $this->table->token), $this->orderPayload([
            ['product_id' => $product->id, 'qty' => 5],
        ]));

        $order = DineOrder::firstOrFail();

        // stock drops below ordered qty after the order was submitted
        $this->warehouse->products()->updateExistingPivot($product->id, ['stock' => 2]);
        $product->update(['stock' => 2]);

        $this->actingAs($this->cashier);
        $this->from(route('dine-orders.index'))
            ->post(route('dine-orders.accept', $order))
            ->assertSessionHasErrors('stock');

        $this->assertEquals('submitted', $order->fresh()->status);
        $this->assertEquals(2, $product->fresh()->stock);

        $pivot = ProductWarehouse::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(2, $pivot->stock);
    }

    public function test_accept_without_open_shift_rejected(): void
    {
        $product = $this->createProduct(50);
        $this->actingAs($this->cashier);

        $this->post(route('dine-order.store', $this->table->token), $this->orderPayload([
            ['product_id' => $product->id, 'qty' => 2],
        ]));

        $order = DineOrder::firstOrFail();

        $this->actingAs($this->cashier);
        $this->from(route('dine-orders.index'))
            ->post(route('dine-orders.accept', $order))
            ->assertSessionHasErrors('shift');

        $this->assertEquals('submitted', $order->fresh()->status);
        $this->assertEquals(50, $product->fresh()->stock);
    }

    public function test_accept_rejects_shift_from_another_outlet(): void
    {
        $outletA = Outlet::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'is_active' => true, 'is_sales_enabled' => true]);
        $outletB = Outlet::create(['code' => 'OUT-B', 'name' => 'Outlet B', 'is_active' => true, 'is_sales_enabled' => true]);
        $shiftWarehouse = Warehouse::create([
            'outlet_id' => $outletA->id,
            'code' => 'WH-A',
            'name' => 'Gudang A',
            'type' => 'branch',
            'is_active' => true,
        ]);
        $orderWarehouse = Warehouse::create([
            'outlet_id' => $outletB->id,
            'code' => 'WH-B',
            'name' => 'Gudang B',
            'type' => 'branch',
            'is_active' => true,
        ]);
        $area = DineArea::create(['outlet_id' => $outletB->id, 'name' => 'Area B', 'is_active' => true]);
        $table = DiningTable::create([
            'dine_area_id' => $area->id,
            'name' => 'Meja B',
            'is_active' => true,
            'capacity' => 4,
        ]);
        $product = $this->createProduct(10);
        $product->warehouses()->attach($orderWarehouse->id, ['stock' => 10]);

        $this->post(route('dine-order.store', $table->token), $this->orderPayload([
            ['product_id' => $product->id, 'qty' => 1],
        ]));
        $order = DineOrder::firstOrFail();

        $this->actingAs($this->cashier);
        app(CashierShiftService::class)->openShift($this->cashier, $this->cashier, 0, null, $shiftWarehouse->id);

        $this->post(route('dine-orders.accept', $order))
            ->assertSessionHasErrors('shift');

        $this->assertSame('submitted', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(10, $orderWarehouse->products()->whereKey($product->id)->first()->pivot->stock);
    }

    public function test_status_page_renders_order(): void
    {
        $product = $this->createProduct(10);

        $this->post(route('dine-order.store', $this->table->token), $this->orderPayload([
            ['product_id' => $product->id, 'qty' => 1],
        ]));

        $order = DineOrder::firstOrFail();

        $this->get(route('dine-order.status', $order->access_token))
            ->assertOk()
            ->assertSee('Produk '.self::$seq);
    }

    public function test_store_maps_product_pricing_for_composite(): void
    {
        $componentA = $this->createProduct(10);
        $componentB = $this->createProduct(10);
        $composite = $this->createProduct(0, true);
        $composite->components()->attach([
            $componentA->id => ['qty' => 1],
            $componentB->id => ['qty' => 2],
        ]);

        $this->post(route('dine-order.store', $this->table->token), $this->orderPayload([
            ['product_id' => $composite->id, 'qty' => 1],
        ]))->assertSessionHasNoErrors();

        $order = DineOrder::firstOrFail();
        $this->assertEquals(30000, $order->subtotal);
    }
}
