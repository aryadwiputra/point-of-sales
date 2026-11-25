<?php

namespace Tests\Feature\DineIn;

use App\Models\Category;
use App\Models\DineArea;
use App\Models\DineBill;
use App\Models\DineOrder;
use App\Models\DineOrderItemBatchAllocation;
use App\Models\DineSession;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductWarehouse;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use App\Services\DineOrderService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DineOrderAcceptTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private User $cashier;

    private Warehouse $warehouse;

    private Category $category;

    private DiningTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        $this->cashier = User::where('email', 'cashier@gmail.com')->first();
        $this->cashier->markEmailAsVerified();
        $this->actingAs($this->cashier);
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
            'name' => 'Meja Accept',
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
    }

    private function createProduct(int $stock = 20, int $buyPrice = 4000): Product
    {
        self::$seq++;
        $product = Product::create([
            'title' => 'Menu Accept '.self::$seq,
            'sku' => 'SKU-ACC-'.self::$seq,
            'buy_price' => $buyPrice,
            'sell_price' => 10000,
            'stock' => $stock,
            'image' => 'products/test.jpg',
            'barcode' => 'BC-ACC-'.self::$seq,
            'description' => 'Produk test',
            'tax_rate' => 0,
            'tax_type' => 'exclusive',
            'category_id' => $this->category->id,
        ]);
        $product->warehouses()->attach($this->warehouse->id, ['stock' => $stock]);

        return $product;
    }

    private function createOrder(Product $product, int $qty = 2): DineOrder
    {
        $order = DineOrder::create([
            'dine_table_id' => $this->table->id,
            'access_token' => 'acc-'.uniqid(),
            'status' => DineOrder::STATUS_SUBMITTED,
            'source' => DineOrder::SOURCE_QR,
            'payment_option' => DineOrder::PAY_AT_COUNTER,
            'subtotal' => $product->sell_price * $qty,
            'item_count' => $qty,
            'submitted_at' => now(),
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'qty' => $qty,
            'price' => $product->sell_price,
        ]);

        return $order;
    }

    public function test_accept_creates_session_and_bill_without_transaction(): void
    {
        $product = $this->createProduct();
        $order = $this->createOrder($product, 2);

        app(DineOrderService::class)->accept($order);

        $order = $order->fresh();
        $this->assertSame(DineOrder::STATUS_ACCEPTED, $order->status);
        $this->assertNotNull($order->dine_session_id);
        $this->assertNull($order->transaction_id);
        $this->assertSame(0, Transaction::count());
        $this->assertSame(DineOrder::SOURCE_QR, $order->source);

        $bill = DineBill::where('dine_session_id', $order->dine_session_id)->firstOrFail();
        $this->assertSame(20000, $bill->subtotal);
        $this->assertSame($bill->id, $order->items()->first()->dine_bill_id);

        $item = $order->items()->first();
        $this->assertSame(4000, $item->unit_cost);
        $this->assertSame('exclusive', $item->tax_type);
        $this->assertSame(18, $product->fresh()->stock);
    }

    public function test_accept_records_batch_allocations_in_the_dine_table(): void
    {
        $product = $this->createProduct(20);
        $batch = ProductBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-ACC',
            'received_at' => now()->subDay(),
            'stock' => 20,
        ]);
        $order = $this->createOrder($product, 3);

        app(DineOrderService::class)->accept($order);

        $item = $order->fresh()->items()->first();
        $this->assertSame(17, $batch->fresh()->stock);
        $this->assertDatabaseHas('dine_order_item_batch_allocations', [
            'dine_order_item_id' => $item->id,
            'product_batch_id' => $batch->id,
            'qty' => 3,
        ]);
        $this->assertSame(1, DineOrderItemBatchAllocation::count());
        $this->assertSame(0, \DB::table('transaction_detail_batch_allocations')->count());
    }

    public function test_accept_aborts_when_stock_is_insufficient(): void
    {
        $product = $this->createProduct(20);
        $order = $this->createOrder($product, 5);

        ProductWarehouse::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->update(['stock' => 2]);
        $product->update(['stock' => 2]);

        try {
            app(DineOrderService::class)->accept($order);
            $this->fail('Expected ValidationException for insufficient stock.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('stock', $exception->errors());
        }

        $this->assertSame(DineOrder::STATUS_SUBMITTED, $order->fresh()->status);
        $this->assertNull($order->fresh()->dine_session_id);
        $this->assertSame(0, DineSession::count());
        $this->assertSame(0, DineBill::count());
        $this->assertSame(2, $product->fresh()->stock);
    }
}
