<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMutation;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class V1InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->markEmailAsVerified();

        $this->warehouseA = Warehouse::create([
            'code' => 'WH-A',
            'name' => 'Gudang A',
            'status' => 'active',
        ]);

        $this->warehouseB = Warehouse::create([
            'code' => 'WH-B',
            'name' => 'Gudang B',
            'status' => 'active',
        ]);

        $category = Category::create([
            'name' => 'Kategori Inventory',
            'image' => '',
            'description' => '',
        ]);

        $this->product = Product::create([
            'title' => 'Produk Inventory',
            'barcode' => 'INV-'.uniqid(),
            'sku' => 'SKU-INV-'.uniqid(),
            'image' => '',
            'description' => '',
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 50,
            'category_id' => $category->id,
            'tax_type' => 'exclusive',
            'tax_rate' => 0,
            'min_stock' => 0,
            'max_stock' => 100,
            'is_composite' => false,
        ]);

        $this->product->warehouses()->attach($this->warehouseA->id, ['stock' => 50]);
        $this->product->warehouses()->attach($this->warehouseB->id, ['stock' => 0]);

        Sanctum::actingAs($this->user, ['*']);
    }

    // --- Stock Transfers ---

    public function test_stock_transfer_full_flow_create_send_receive(): void
    {
        $transfer = StockTransfer::create([
            'source_warehouse_id' => $this->warehouseA->id,
            'destination_warehouse_id' => $this->warehouseB->id,
            'document_number' => 'TRF-'.uniqid(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);
        $transfer->items()->create([
            'product_id' => $this->product->id,
            'qty' => 10,
        ]);

        $this->postJson('/api/v1/stock-transfers/'.$transfer->id.'/send')
            ->assertOk()
            ->assertJsonPath('data.status', 'in_transit');

        $this->assertDatabaseHas('product_warehouse', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouseA->id,
            'stock' => 40,
        ]);

        $this->postJson('/api/v1/stock-transfers/'.$transfer->id.'/receive')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('product_warehouse', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouseB->id,
            'stock' => 10,
        ]);

        $this->assertSame(50, (int) $this->product->fresh()->stock);
    }

    public function test_stock_transfer_store_creates_draft(): void
    {
        $response = $this->postJson('/api/v1/stock-transfers', [
            'source_warehouse_id' => $this->warehouseA->id,
            'destination_warehouse_id' => $this->warehouseB->id,
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 5],
            ],
        ])->assertCreated()->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('stock_transfers', [
            'id' => $response->json('data.id'),
            'status' => 'draft',
        ]);
    }

    public function test_stock_transfer_store_rejects_same_warehouse(): void
    {
        $this->postJson('/api/v1/stock-transfers', [
            'source_warehouse_id' => $this->warehouseA->id,
            'destination_warehouse_id' => $this->warehouseA->id,
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 5],
            ],
        ])->assertStatus(422);
    }

    public function test_stock_transfer_index_requires_ability(): void
    {
        Sanctum::actingAs($this->user, ['products-access']);

        $this->getJson('/api/v1/stock-transfers')->assertForbidden();
    }

    // --- Stock Opnames ---

    public function test_stock_opname_flow_create_item_finalize_adjusts_stock(): void
    {
        $opname = $this->postJson('/api/v1/stock-opnames', [
            'warehouse_id' => $this->warehouseA->id,
            'notes' => 'Opname rutin',
        ])->assertCreated()->assertJsonPath('data.status', 'draft');

        $opnameId = $opname->json('data.id');

        $item = $this->postJson('/api/v1/stock-opnames/'.$opnameId.'/items', [
            'product_id' => $this->product->id,
        ])->assertCreated()->json('data.id');

        $this->patchJson('/api/v1/stock-opnames/'.$opnameId.'/items/'.$item, [
            'physical_stock' => 45,
            'adjustment_reason' => 'Barang rusak',
        ])->assertOk()->assertJsonPath('data.difference', -5);

        $this->postJson('/api/v1/stock-opnames/'.$opnameId.'/finalize')
            ->assertOk()
            ->assertJsonPath('data.status', 'finalized');

        $this->assertDatabaseHas('product_warehouse', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouseA->id,
            'stock' => 45,
        ]);

        $this->assertSame(45, (int) $this->product->fresh()->stock);

        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $this->product->id,
            'mutation_type' => 'adjustment',
            'reference_type' => 'stock_opname',
            'reference_id' => $opnameId,
        ]);
    }

    public function test_stock_opname_item_requires_reason_when_difference_exists(): void
    {
        $opname = StockOpname::create([
            'code' => 'OPN-'.uniqid(),
            'warehouse_id' => $this->warehouseA->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $item = $opname->items()->create([
            'product_id' => $this->product->id,
            'system_stock' => 50,
        ]);

        $this->patchJson('/api/v1/stock-opnames/'.$opname->id.'/items/'.$item->id, [
            'physical_stock' => 40,
        ])->assertStatus(422);
    }

    public function test_stock_opname_finalize_requires_dedicated_ability(): void
    {
        Sanctum::actingAs($this->user, ['stock-opnames-access']);

        $opname = StockOpname::create([
            'code' => 'OPN-'.uniqid(),
            'warehouse_id' => $this->warehouseA->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $this->postJson('/api/v1/stock-opnames/'.$opname->id.'/finalize')
            ->assertForbidden();
    }

    // --- Stock Mutations ---

    public function test_stock_mutations_index_lists_recorded_mutations(): void
    {
        StockMutation::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouseA->id,
            'reference_type' => 'stock_transfer',
            'reference_id' => 1,
            'mutation_type' => 'out',
            'qty' => 10,
            'stock_before' => 50,
            'stock_after' => 40,
            'notes' => 'Transfer keluar',
            'user_id' => $this->user->id,
        ]);

        $this->getJson('/api/v1/stock-mutations')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.mutation_type', 'out');
    }

    // --- Purchase Orders ---

    public function test_purchase_order_flow_store_place_cancel(): void
    {
        $supplier = Supplier::create([
            'name' => 'Supplier Test',
            'phone' => '0812',
            'email' => 'supplier@test.com',
            'address' => 'Jl. Test',
        ]);

        $order = $this->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $this->warehouseA->id,
            'items' => [
                ['product_id' => $this->product->id, 'qty_ordered' => 20, 'unit_price' => 5000],
            ],
        ])->assertCreated()->assertJsonPath('data.status', 'draft');

        $orderId = $order->json('data.id');

        $this->postJson('/api/v1/purchase-orders/'.$orderId.'/place')
            ->assertOk()
            ->assertJsonPath('data.status', 'ordered');

        $this->postJson('/api/v1/purchase-orders/'.$orderId.'/place')
            ->assertStatus(422);

        $this->postJson('/api/v1/purchase-orders/'.$orderId.'/cancel')
            ->assertOk();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $orderId,
            'status' => 'cancelled',
        ]);
    }

    public function test_purchase_order_place_requires_update_ability(): void
    {
        Sanctum::actingAs($this->user, ['purchase-orders-access', 'purchase-orders-create']);

        $order = PurchaseOrder::create([
            'supplier_id' => null,
            'warehouse_id' => $this->warehouseA->id,
            'document_number' => 'PO-'.uniqid(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $this->postJson('/api/v1/purchase-orders/'.$order->id.'/place')
            ->assertForbidden();
    }

    // --- Goods Receivings ---

    public function test_goods_receiving_store_increments_stock(): void
    {
        $order = PurchaseOrder::create([
            'supplier_id' => null,
            'warehouse_id' => $this->warehouseA->id,
            'document_number' => 'PO-'.uniqid(),
            'status' => 'ordered',
            'created_by' => $this->user->id,
        ]);
        $poItem = $order->items()->create([
            'product_id' => $this->product->id,
            'qty_ordered' => 20,
            'qty_received' => 0,
            'unit_price' => 5000,
        ]);

        $this->postJson('/api/v1/goods-receivings', [
            'purchase_order_id' => $order->id,
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'qty_received' => 15],
            ],
        ])->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('product_warehouse', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouseA->id,
            'stock' => 65,
        ]);

        $this->assertSame(65, (int) $this->product->fresh()->stock);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $order->id,
            'status' => 'partial_received',
        ]);
    }

    public function test_goods_receiving_rejects_qty_over_outstanding(): void
    {
        $order = PurchaseOrder::create([
            'supplier_id' => null,
            'warehouse_id' => $this->warehouseA->id,
            'document_number' => 'PO-'.uniqid(),
            'status' => 'ordered',
            'created_by' => $this->user->id,
        ]);
        $poItem = $order->items()->create([
            'product_id' => $this->product->id,
            'qty_ordered' => 10,
            'qty_received' => 0,
            'unit_price' => 5000,
        ]);

        $this->postJson('/api/v1/goods-receivings', [
            'purchase_order_id' => $order->id,
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'qty_received' => 11],
            ],
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    // --- Supplier Returns ---

    public function test_supplier_return_flow_create_complete(): void
    {
        $supplier = Supplier::create([
            'name' => 'Supplier Return',
            'phone' => '0813',
            'email' => 'sr@test.com',
            'address' => 'Jl. SR',
        ]);

        $return = $this->postJson('/api/v1/supplier-returns', [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $this->warehouseA->id,
            'items' => [
                ['product_id' => $this->product->id, 'qty_returned' => 5, 'unit_price' => 5000],
            ],
        ])->assertCreated();

        $returnId = $return->json('data.id');

        $this->postJson('/api/v1/supplier-returns/'.$returnId.'/complete')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->postJson('/api/v1/supplier-returns/'.$returnId.'/cancel')
            ->assertStatus(422);
    }

    public function test_supplier_return_cancel_draft(): void
    {
        $return = SupplierReturn::create([
            'supplier_id' => null,
            'warehouse_id' => $this->warehouseA->id,
            'document_number' => 'SR-'.uniqid(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);
        $return->items()->create([
            'product_id' => $this->product->id,
            'qty_returned' => 1,
            'unit_price' => 5000,
        ]);

        $this->postJson('/api/v1/supplier-returns/'.$return->id.'/cancel')
            ->assertOk();

        $this->assertDatabaseHas('supplier_returns', [
            'id' => $return->id,
            'status' => 'cancelled',
        ]);
    }
}
