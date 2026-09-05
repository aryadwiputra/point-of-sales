<?php

namespace Tests\Feature\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductWarehouse;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchFefoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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

        $this->admin = User::where('email', 'arya@gmail.com')->first();
        $this->admin->markEmailAsVerified();
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
            'sku' => 'SKU-BATCH-'.self::$seq,
            'barcode' => 'BC-BATCH-'.self::$seq,
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

    private function startShift(): void
    {
        $this->cashierShiftService->openShift($this->cashier, $this->cashier, 0, null, $this->pusat->id);
    }

    private function addToCart(Product $product, int $qty = 1): void
    {
        $this->actingAs($this->cashier)
            ->post(route('transactions.addToCart'), [
                'product_id' => $product->id,
                'sell_price' => $product->sell_price,
                'qty' => $qty,
            ])
            ->assertRedirect();
    }

    public function test_checkout_decrements_fefo_batch_first(): void
    {
        $product = $this->createProduct(30);
        $batchA = $this->createBatch($product, 'BATCH-A', now()->addDays(10)->toDateString(), 5);
        $batchB = $this->createBatch($product, 'BATCH-B', now()->addDays(60)->toDateString(), 20);

        $this->startShift();
        $this->addToCart($product, 8);
        $this->actingAs($this->cashier)
            ->post(route('transactions.store'), ['payment_method' => 'cash', 'cash' => 100000])
            ->assertRedirect();

        $this->assertEquals(0, $batchA->fresh()->stock);
        $this->assertEquals(17, $batchB->fresh()->stock);
        $this->assertEquals(22, $product->fresh()->stock);
        $this->assertEquals(22, ProductWarehouse::where('product_id', $product->id)
            ->where('warehouse_id', $this->pusat->id)->value('stock'));

        $detail = TransactionDetail::latest('id')->first();
        $this->assertEquals($batchA->id, $detail->product_batch_id);
    }

    public function test_checkout_without_batches_keeps_detail_batch_null(): void
    {
        $product = $this->createProduct(10);

        $this->startShift();
        $this->addToCart($product, 2);
        $this->actingAs($this->cashier)
            ->post(route('transactions.store'), ['payment_method' => 'cash', 'cash' => 50000])
            ->assertRedirect();

        $detail = TransactionDetail::latest('id')->first();
        $this->assertNull($detail->product_batch_id);
        $this->assertEquals(8, $product->fresh()->stock);
    }

    public function test_expiring_soon_scope_returns_batches_within_30_days(): void
    {
        $product = $this->createProduct(10);
        $soon = $this->createBatch($product, 'SOON', now()->addDays(10)->toDateString(), 5);
        $this->createBatch($product, 'LATER', now()->addDays(90)->toDateString(), 5);

        $ids = ProductBatch::expiringSoon(30)->pluck('id');

        $this->assertContains($soon->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_expired_scope_excludes_non_expired(): void
    {
        $product = $this->createProduct(10);
        $expired = $this->createBatch($product, 'OLD', now()->subDays(5)->toDateString(), 5);
        $this->createBatch($product, 'OK', now()->addDays(90)->toDateString(), 5);

        $ids = ProductBatch::expired()->pluck('id');

        $this->assertContains($expired->id, $ids);
        $this->assertCount(1, $ids);
    }
}
