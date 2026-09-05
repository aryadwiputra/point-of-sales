<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockMutation;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Warehouse $source;

    protected Warehouse $destination;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        $this->admin = User::where('email', 'arya@gmail.com')->first();
        $this->admin->markEmailAsVerified();
        $this->actingAs($this->admin);

        $this->source = Warehouse::create([
            'code' => 'GDG-A',
            'name' => 'Gudang A',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->destination = Warehouse::create([
            'code' => 'GDG-B',
            'name' => 'Gudang B',
            'type' => 'branch',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->category = Category::create([
            'name' => 'Kategori Transfer',
            'image' => 'categories/test.jpg',
            'description' => 'Kategori untuk test transfer',
        ]);
    }

    private static int $seq = 0;

    private function createProduct(int $sourceStock, ?int $destStock = null): Product
    {
        self::$seq++;

        $product = Product::create([
            'title' => 'Produk Transfer '.self::$seq,
            'sku' => 'SKU-ST-'.self::$seq,
            'buy_price' => 10000,
            'sell_price' => 15000,
            'stock' => $sourceStock,
            'image' => 'products/test.jpg',
            'barcode' => 'BC-ST-'.self::$seq,
            'description' => 'Deskripsi produk transfer',
            'tax_rate' => 0,
            'category_id' => $this->category->id,
        ]);

        $product->warehouses()->attach($this->source->id, ['stock' => $sourceStock]);

        if ($destStock !== null) {
            $product->warehouses()->attach($this->destination->id, ['stock' => $destStock]);
        }

        return $product;
    }

    private function createDraft(Product $product, int $qty): StockTransfer
    {
        return app(StockTransferService::class)->createDraft(
            data: [
                'source_warehouse_id' => $this->source->id,
                'destination_warehouse_id' => $this->destination->id,
            ],
            items: [[
                'product_id' => $product->id,
                'qty' => $qty,
            ]],
            userId: $this->admin->id
        );
    }

    private function pivotStock(int $productId, int $warehouseId): int
    {
        $pw = ProductWarehouse::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        return $pw ? (int) $pw->stock : 0;
    }

    public function test_send_moves_stock_to_in_transit_and_records_out_mutation(): void
    {
        $product = $this->createProduct(50);
        $transfer = $this->createDraft($product, 10);

        $this->post(route('stock-transfers.send', $transfer))
            ->assertSessionHas('success');

        $this->assertEquals('in_transit', $transfer->fresh()->status);
        $this->assertEquals(40, $product->fresh()->stock);
        $this->assertEquals(40, $this->pivotStock($product->id, $this->source->id));

        $mutation = StockMutation::where('reference_type', 'stock_transfer')
            ->where('reference_id', $transfer->id)
            ->where('mutation_type', 'out')
            ->first();
        $this->assertNotNull($mutation);
        $this->assertEquals(50, $mutation->stock_before);
        $this->assertEquals(40, $mutation->stock_after);
    }

    public function test_send_rejects_draft_that_is_not_draft_anymore(): void
    {
        $product = $this->createProduct(50);
        $transfer = $this->createDraft($product, 10);
        $transfer->update(['status' => 'in_transit']);

        $this->from(route('stock-transfers.index'))
            ->post(route('stock-transfers.send', $transfer->fresh()))
            ->assertSessionHasErrors('transfer');

        $this->assertEquals(50, $product->fresh()->stock);
    }

    public function test_send_rejects_insufficient_source_stock(): void
    {
        $product = $this->createProduct(5);
        $transfer = $this->createDraft($product, 10);

        $this->from(route('stock-transfers.index'))
            ->post(route('stock-transfers.send', $transfer))
            ->assertSessionHasErrors('transfer');

        $this->assertEquals('draft', $transfer->fresh()->status);
        $this->assertEquals(5, $product->fresh()->stock);
        $this->assertEquals(5, $this->pivotStock($product->id, $this->source->id));
        $this->assertEquals(0, $this->pivotStock($product->id, $this->destination->id));
    }

    public function test_receive_increments_destination_and_completes(): void
    {
        $product = $this->createProduct(50, 0);
        $transfer = $this->createDraft($product, 10);
        app(StockTransferService::class)->send($transfer, $this->admin->id);

        $this->post(route('stock-transfers.receive', $transfer->fresh()))
            ->assertSessionHas('success');

        $this->assertEquals('completed', $transfer->fresh()->status);
        $this->assertNotNull($transfer->fresh()->completed_at);
        $this->assertEquals(50, $product->fresh()->stock);
        $this->assertEquals(40, $this->pivotStock($product->id, $this->source->id));
        $this->assertEquals(10, $this->pivotStock($product->id, $this->destination->id));

        $mutation = StockMutation::where('reference_type', 'stock_transfer')
            ->where('reference_id', $transfer->id)
            ->where('mutation_type', 'in')
            ->first();
        $this->assertNotNull($mutation);
        $this->assertEquals(40, $mutation->stock_before);
        $this->assertEquals(50, $mutation->stock_after);
    }

    public function test_double_receive_is_rejected(): void
    {
        $product = $this->createProduct(50);
        $transfer = $this->createDraft($product, 10);
        $service = app(StockTransferService::class);
        $service->send($transfer, $this->admin->id);
        $service->receive($transfer->fresh(), $this->admin->id);

        $this->from(route('stock-transfers.index'))
            ->post(route('stock-transfers.receive', $transfer->fresh()))
            ->assertSessionHasErrors('transfer');

        $this->assertEquals('completed', $transfer->fresh()->status);
        $this->assertEquals(50, $product->fresh()->stock);
        $this->assertEquals(40, $this->pivotStock($product->id, $this->source->id));
        $this->assertEquals(10, $this->pivotStock($product->id, $this->destination->id));
    }

    public function test_cancel_in_transit_returns_stock_to_source(): void
    {
        $product = $this->createProduct(50);
        $transfer = $this->createDraft($product, 10);
        $service = app(StockTransferService::class);
        $service->send($transfer, $this->admin->id);

        $this->post(route('stock-transfers.cancel', $transfer->fresh()))
            ->assertSessionHas('success');

        $this->assertEquals('cancelled', $transfer->fresh()->status);
        $this->assertEquals(50, $product->fresh()->stock);
        $this->assertEquals(50, $this->pivotStock($product->id, $this->source->id));
        $this->assertEquals(0, $this->pivotStock($product->id, $this->destination->id));
    }

    public function test_cancel_draft_does_not_touch_stock(): void
    {
        $product = $this->createProduct(50);
        $transfer = $this->createDraft($product, 10);

        $this->post(route('stock-transfers.cancel', $transfer))
            ->assertSessionHas('success');

        $this->assertEquals('cancelled', $transfer->fresh()->status);
        $this->assertEquals(50, $product->fresh()->stock);
        $this->assertEquals(50, $this->pivotStock($product->id, $this->source->id));
    }

    public function test_cancel_completed_transfer_is_rejected(): void
    {
        $product = $this->createProduct(50);
        $transfer = $this->createDraft($product, 10);
        $service = app(StockTransferService::class);
        $service->send($transfer, $this->admin->id);
        $service->receive($transfer->fresh(), $this->admin->id);

        $this->from(route('stock-transfers.index'))
            ->post(route('stock-transfers.cancel', $transfer->fresh()))
            ->assertSessionHasErrors('transfer');

        $this->assertEquals('completed', $transfer->fresh()->status);
        $this->assertEquals(50, $product->fresh()->stock);
        $this->assertEquals(40, $this->pivotStock($product->id, $this->source->id));
        $this->assertEquals(10, $this->pivotStock($product->id, $this->destination->id));
    }

    public function test_receiving_creates_destination_pivot_when_missing(): void
    {
        $product = $this->createProduct(50); // no destination pivot row
        $transfer = $this->createDraft($product, 10);
        $service = app(StockTransferService::class);
        $service->send($transfer, $this->admin->id);

        $service->receive($transfer->fresh(), $this->admin->id);

        $this->assertEquals(10, $this->pivotStock($product->id, $this->destination->id));
        $this->assertEquals(40, $this->pivotStock($product->id, $this->source->id));
        $this->assertEquals(50, $product->fresh()->stock);
    }
}
