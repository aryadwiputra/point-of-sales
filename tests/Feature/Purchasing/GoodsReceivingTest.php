<?php

namespace Tests\Feature\Purchasing;

use App\Models\Category;
use App\Models\GoodsReceiving;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\PurchaseOrder;
use App\Models\StockMutation;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseOrderService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoodsReceivingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        $this->admin = User::where('email', 'arya@gmail.com')->first();
        $this->admin->markEmailAsVerified();
        $this->actingAs($this->admin);

        $this->category = Category::create([
            'name' => 'Kategori Test',
            'image' => 'categories/test.jpg',
            'description' => 'Kategori untuk test',
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Supplier Test',
            'phone' => '081234567890',
            'email' => 'supplier@test.com',
        ]);
    }

    private static int $seq = 0;

    private function createProduct(int $stock = 100): Product
    {
        self::$seq++;

        $product = Product::create([
            'title' => 'Produk '.self::$seq,
            'sku' => 'SKU-PO-'.self::$seq,
            'buy_price' => 10000,
            'sell_price' => 15000,
            'stock' => $stock,
            'image' => 'products/test.jpg',
            'barcode' => 'BC-PO-'.self::$seq,
            'description' => 'Deskripsi produk test',
            'tax_rate' => 0,
            'category_id' => $this->category->id,
        ]);

        $product->warehouses()->attach($this->warehouse->id, ['stock' => $stock]);

        return $product;
    }

    private function createOrderedPo(Product $product, int $qty = 10, ?int $warehouseId = null): PurchaseOrder
    {
        $service = app(PurchaseOrderService::class);

        $order = $service->createOrder(
            data: [
                'supplier_id' => $this->supplier->id,
                'warehouse_id' => $warehouseId,
            ],
            items: [[
                'product_id' => $product->id,
                'qty_ordered' => $qty,
                'unit_price' => 5000,
            ]],
            userId: $this->admin->id
        );

        $service->placeOrder($order);

        return $order->fresh();
    }

    private function receivingPayload(PurchaseOrder $order, int $qty): array
    {
        return [
            'purchase_order_id' => $order->id,
            'items' => [[
                'purchase_order_item_id' => $order->items->first()->id,
                'qty_received' => $qty,
            ]],
        ];
    }

    public function test_receiving_increments_global_and_warehouse_stock(): void
    {
        $product = $this->createProduct(100);
        $order = $this->createOrderedPo($product, 10, $this->warehouse->id);

        $response = $this->post(route('goods-receivings.store'), $this->receivingPayload($order, 4));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $product = $product->fresh();
        $this->assertEquals(104, $product->stock);

        $pivot = ProductWarehouse::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertNotNull($pivot);
        $this->assertEquals(104, $pivot->stock);

        $poItem = $order->items()->first();
        $this->assertEquals(4, $poItem->qty_received);
        $this->assertEquals('partial_received', $order->fresh()->status);

        $mutation = StockMutation::where('reference_type', 'goods_receiving')
            ->where('product_id', $product->id)
            ->first();
        $this->assertNotNull($mutation);
        $this->assertEquals('in', $mutation->mutation_type);
        $this->assertEquals(4, $mutation->qty);
        $this->assertEquals(100, $mutation->stock_before);
        $this->assertEquals(104, $mutation->stock_after);
    }

    public function test_receiving_rejects_draft_po(): void
    {
        $product = $this->createProduct(100);
        $order = app(PurchaseOrderService::class)->createOrder(
            data: ['supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id],
            items: [['product_id' => $product->id, 'qty_ordered' => 10, 'unit_price' => 5000]],
            userId: $this->admin->id
        );

        $response = $this->post(route('goods-receivings.store'), $this->receivingPayload($order, 4));

        $response->assertSessionHas('error');
        $this->assertEquals(0, GoodsReceiving::count());
        $this->assertEquals(100, $product->fresh()->stock);
        $this->assertEquals('draft', $order->fresh()->status);
    }

    public function test_receiving_rejects_qty_exceeding_outstanding(): void
    {
        $product = $this->createProduct(100);
        $order = $this->createOrderedPo($product, 10, $this->warehouse->id);

        $response = $this->post(route('goods-receivings.store'), $this->receivingPayload($order, 11));

        $response->assertSessionHas('error');
        $this->assertEquals(0, GoodsReceiving::count());
        $this->assertEquals(100, $product->fresh()->stock);
    }

    public function test_receiving_without_warehouse_still_increments_global_stock(): void
    {
        $product = $this->createProduct(100);
        $order = $this->createOrderedPo($product, 10, null);

        $response = $this->post(route('goods-receivings.store'), $this->receivingPayload($order, 4));

        $response->assertSessionHas('success');
        $this->assertEquals(104, $product->fresh()->stock);
        $this->assertEquals('partial_received', $order->fresh()->status);
    }

    public function test_receiving_with_batch_number_creates_batch_record(): void
    {
        $product = $this->createProduct(100);
        $order = $this->createOrderedPo($product, 10, $this->warehouse->id);

        $payload = $this->receivingPayload($order, 5);
        $payload['items'][0]['batch_number'] = 'BATCH-001';
        $payload['items'][0]['expired_at'] = '2027-12-31';

        $response = $this->post(route('goods-receivings.store'), $payload);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('product_batches', [
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BATCH-001',
            'stock' => 5,
        ]);
    }

    public function test_full_receiving_marks_po_completed(): void
    {
        $product = $this->createProduct(100);
        $order = $this->createOrderedPo($product, 10, $this->warehouse->id);

        $this->post(route('goods-receivings.store'), $this->receivingPayload($order, 10));

        $this->assertEquals('completed', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->completed_at);
    }

    public function test_receiving_rejects_duplicate_po_item_in_one_payload(): void
    {
        $product = $this->createProduct(100);
        $order = $this->createOrderedPo($product, 10, $this->warehouse->id);
        $poItemId = $order->items->first()->id;

        $payload = [
            'purchase_order_id' => $order->id,
            'items' => [
                ['purchase_order_item_id' => $poItemId, 'qty_received' => 4],
                ['purchase_order_item_id' => $poItemId, 'qty_received' => 4],
            ],
        ];

        $response = $this->post(route('goods-receivings.store'), $payload);

        $response->assertSessionHas('error');
        $this->assertEquals(0, GoodsReceiving::count());
        $this->assertEquals(100, $product->fresh()->stock);
        $this->assertEquals(0, $order->items->first()->fresh()->qty_received);
    }

    public function test_receiving_service_rejects_duplicate_items_each_within_outstanding(): void
    {
        $product = $this->createProduct(100);
        $order = $this->createOrderedPo($product, 10, $this->warehouse->id);
        $poItemId = $order->items->first()->id;

        $this->post(route('goods-receivings.store'), [
            'purchase_order_id' => $order->id,
            'items' => [
                ['purchase_order_item_id' => $poItemId, 'qty_received' => 6],
                ['purchase_order_item_id' => $poItemId, 'qty_received' => 6],
            ],
        ])->assertSessionHas('error');

        $this->assertEquals(0, GoodsReceiving::count());
        $this->assertEquals(100, $product->fresh()->stock);
        $this->assertEquals(0, $order->items->first()->fresh()->qty_received);
        $this->assertEquals('ordered', $order->fresh()->status);
    }
}
