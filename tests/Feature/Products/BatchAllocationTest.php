<?php

namespace Tests\Feature\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\TransactionDetail;
use App\Models\TransactionDetailBatchAllocation;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchAllocationTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Warehouse $pusat;

    private Category $category;

    private CashierShiftService $cashierShiftService;

    private static int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
        ]);

        $this->cashier = User::where('email', 'cashier@gmail.com')->first();
        $this->cashier->markEmailAsVerified();

        $this->cashierShiftService = app(CashierShiftService::class);

        $this->pusat = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->category = Category::create([
            'name' => 'Test Category',
            'image' => 'test.png',
            'description' => 'desc',
        ]);
    }

    private function createProduct(int $stock = 0): Product
    {
        self::$seq++;

        $product = Product::create([
            'title' => 'Batched Product '.self::$seq,
            'sku' => 'SKU-BATCHALLOC-'.self::$seq,
            'barcode' => 'BC-BATCHALLOC-'.self::$seq,
            'image' => 'product.png',
            'description' => 'desc',
            'category_id' => $this->category->id,
            'buy_price' => 1000,
            'sell_price' => 2000,
            'stock' => $stock,
            'tax_rate' => 0,
        ]);

        $product->warehouses()->attach($this->pusat->id, ['stock' => $stock]);

        return $product;
    }

    private function createBatch(Product $product, string $batchNumber, string $expiredAt, int $stock): ProductBatch
    {
        return ProductBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->pusat->id,
            'batch_number' => $batchNumber,
            'expired_at' => $expiredAt,
            'received_at' => '2026-01-01',
            'stock' => $stock,
        ]);
    }

    public function test_checkout_records_every_batch_allocation(): void
    {
        $product = $this->createProduct(30);
        $batchA = $this->createBatch($product, 'BATCH-A', now()->addDays(10)->toDateString(), 5);
        $batchB = $this->createBatch($product, 'BATCH-B', now()->addDays(60)->toDateString(), 20);

        $this->cashierShiftService->openShift($this->cashier, $this->cashier, 0, null, $this->pusat->id);

        $this->actingAs($this->cashier)
            ->post(route('transactions.addToCart'), [
                'product_id' => $product->id,
                'sell_price' => $product->sell_price,
                'qty' => 8,
            ])
            ->assertRedirect();

        $this->actingAs($this->cashier)
            ->post(route('transactions.store'), ['payment_method' => 'cash', 'cash' => 100000])
            ->assertRedirect();

        $detail = TransactionDetail::latest('id')->first();
        // legacy first-batch column points at the earliest-expiring batch
        $this->assertEquals($batchA->id, $detail->product_batch_id);

        $allocations = TransactionDetailBatchAllocation::where('transaction_detail_id', $detail->id)
            ->orderBy('product_batch_id')
            ->get();
        $this->assertCount(2, $allocations);
        $this->assertEquals($batchA->id, $allocations->first()->product_batch_id);
        $this->assertEquals(5, $allocations->first()->qty);
        $this->assertEquals($batchB->id, $allocations->last()->product_batch_id);
        $this->assertEquals(3, $allocations->last()->qty);

        $this->assertEquals(0, $batchA->fresh()->stock);
        $this->assertEquals(17, $batchB->fresh()->stock);
    }

    public function test_checkout_without_batches_creates_no_allocations(): void
    {
        $product = $this->createProduct(10);

        $this->cashierShiftService->openShift($this->cashier, $this->cashier, 0, null, $this->pusat->id);

        $this->actingAs($this->cashier)
            ->post(route('transactions.addToCart'), [
                'product_id' => $product->id,
                'sell_price' => $product->sell_price,
                'qty' => 2,
            ])
            ->assertRedirect();

        $this->actingAs($this->cashier)
            ->post(route('transactions.store'), ['payment_method' => 'cash', 'cash' => 50000])
            ->assertRedirect();

        $detail = TransactionDetail::latest('id')->first();
        $this->assertNull($detail->product_batch_id);
        $this->assertEquals(0, TransactionDetailBatchAllocation::where('transaction_detail_id', $detail->id)->count());
    }
}
