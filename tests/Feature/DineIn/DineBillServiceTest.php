<?php

namespace Tests\Feature\DineIn;

use App\Models\Category;
use App\Models\DineArea;
use App\Models\DineBill;
use App\Models\DineOrder;
use App\Models\DineSession;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductWarehouse;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DineBillService;
use App\Services\DineSessionService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DineBillServiceTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private User $admin;

    private Warehouse $warehouse;

    private Category $category;

    private DiningTable $table;

    private DineSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        $this->admin = User::where('email', 'arya@gmail.com')->first();
        $this->admin->markEmailAsVerified();
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
            'name' => 'Meja Bill',
            'is_active' => true,
            'capacity' => 4,
        ]);
        $this->session = app(DineSessionService::class)->open(
            $this->table,
            2,
            null,
            $this->admin->id,
        );
    }

    private function createProduct(int $stock = 20): Product
    {
        self::$seq++;

        $product = Product::create([
            'title' => 'Menu '.self::$seq,
            'sku' => 'SKU-BILL-'.self::$seq,
            'buy_price' => 4000,
            'sell_price' => 10000,
            'stock' => $stock,
            'image' => 'products/test.jpg',
            'barcode' => 'BC-BILL-'.self::$seq,
            'description' => 'Produk test',
            'tax_rate' => 0,
            'category_id' => $this->category->id,
        ]);
        $product->warehouses()->attach($this->warehouse->id, ['stock' => $stock]);

        return $product;
    }

    private function createItem(DineBill $bill, Product $product, int $qty = 1, int $unitCost = 4000): DineOrder
    {
        $order = DineOrder::create([
            'dine_table_id' => $this->table->id,
            'dine_session_id' => $this->session->id,
            'access_token' => 'bill-token-'.uniqid(),
            'status' => DineOrder::STATUS_ACCEPTED,
            'source' => DineOrder::SOURCE_QR,
            'subtotal' => $product->sell_price * $qty,
        ]);

        $order->items()->create([
            'dine_bill_id' => $bill->id,
            'product_id' => $product->id,
            'qty' => $qty,
            'price' => $product->sell_price,
            'unit_cost' => $unitCost,
            'tax_type' => 'exclusive',
            'tax_rate' => 0,
        ]);

        return $order;
    }

    public function test_create_next_bill_uses_the_next_sequence(): void
    {
        $service = app(DineBillService::class);
        $first = $service->createNextBill($this->session, $this->admin->id);
        $second = $service->createNextBill($this->session, $this->admin->id);

        $this->assertSame(1, $first->sequence);
        $this->assertSame(2, $second->sequence);
        $this->assertSame(DineBill::STATUS_OPEN, $second->status);
    }

    public function test_split_moves_selected_items_and_voids_emptied_source(): void
    {
        $service = app(DineBillService::class);
        $bill = $service->createNextBill($this->session, $this->admin->id);
        $product = $this->createProduct();
        $order = $this->createItem($bill, $product, 2);
        $item = $order->items()->first();
        $service->recomputeTotals($bill);

        $newBill = $service->split($bill, [$item->id], $this->admin->id);

        $this->assertSame($newBill->id, $item->fresh()->dine_bill_id);
        $this->assertSame(2, $newBill->sequence);
        $this->assertSame(DineBill::STATUS_VOID, $bill->fresh()->status);
        $this->assertSame(20000, $newBill->fresh()->subtotal);
    }

    public function test_split_rejects_pending_approval_bill(): void
    {
        $service = app(DineBillService::class);
        $bill = $service->createNextBill($this->session, $this->admin->id);
        $product = $this->createProduct();
        $order = $this->createItem($bill, $product);
        $bill->update(['status' => DineBill::STATUS_PENDING_APPROVAL]);

        $this->expectException(ValidationException::class);
        $service->split($bill, [$order->items()->first()->id], $this->admin->id);
    }

    public function test_void_item_restores_stock_batches_writes_mutation_and_voids_empty_bill(): void
    {
        $service = app(DineBillService::class);
        $bill = $service->createNextBill($this->session, $this->admin->id);
        $product = $this->createProduct(20);
        $order = $this->createItem($bill, $product, 2);
        $item = $order->items()->first();

        $product->decrement('stock', 2);
        ProductWarehouse::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->decrement('stock', 2);
        $batch = ProductBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-1',
            'received_at' => now()->subDay(),
            'stock' => 18,
        ]);
        $item->batchAllocations()->create([
            'product_batch_id' => $batch->id,
            'qty' => 2,
        ]);

        $service->voidItem($bill, $item, 'Salah input', $this->admin->id, $this->warehouse->id);

        $this->assertNotNull($item->fresh()->voided_at);
        $this->assertSame(20, $product->fresh()->stock);
        $this->assertSame(20, ProductWarehouse::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)->first()->stock);
        $this->assertSame(20, $batch->fresh()->stock);
        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'reference_type' => 'dine_bill',
            'reference_id' => $bill->id,
            'mutation_type' => 'in',
            'qty' => 2,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'dine_bill.item_voided',
            'auditable_id' => $bill->id,
        ]);
        $this->assertSame(DineBill::STATUS_VOID, $bill->fresh()->status);
        $this->assertSame(DineOrder::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(DineSession::STATUS_CLOSED, $this->session->fresh()->status);
    }

    public function test_void_requires_a_reason(): void
    {
        $service = app(DineBillService::class);
        $bill = $service->createNextBill($this->session, $this->admin->id);
        $product = $this->createProduct();
        $order = $this->createItem($bill, $product);

        $this->expectException(ValidationException::class);
        $service->voidItem($bill, $order->items()->first(), '   ', $this->admin->id, $this->warehouse->id);
    }
}
