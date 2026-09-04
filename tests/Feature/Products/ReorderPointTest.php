<?php

namespace Tests\Feature\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReorderPointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        $this->actingAs(User::where('email', 'arya@gmail.com')->first());

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
    }

    private static int $seq = 0;

    private function createProduct(array $overrides = []): Product
    {
        self::$seq++;

        $product = Product::create(array_merge([
            'title' => 'Produk '.self::$seq,
            'sku' => 'SKU-'.self::$seq,
            'buy_price' => 10000,
            'sell_price' => 15000,
            'stock' => 100,
            'image' => 'products/test.jpg',
            'barcode' => 'BC-'.self::$seq,
            'description' => 'Deskripsi produk test',
            'tax_rate' => 0,
            'category_id' => $this->category->id,
        ], $overrides));

        // suggestedOrderQty() reads stock from the product_warehouse pivot
        $product->warehouses()->attach($this->warehouse->id, ['stock' => $product->stock]);

        return $product;
    }

    public function test_generate_creates_draft_po_with_suggested_qty(): void
    {
        $this->createProduct(['min_stock' => 50, 'max_stock' => 200, 'stock' => 40]);

        $this->artisan('reorder:generate')->assertSuccessful();

        $order = PurchaseOrder::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals('draft', $order->status);
        $item = $order->items->first();
        $this->assertEquals(160, $item->qty_ordered); // max 200 - stock 40
        $this->assertEquals(10000, $item->unit_price);
    }

    public function test_generate_skips_low_stock_without_max(): void
    {
        $this->createProduct(['min_stock' => 50, 'stock' => 10]);

        $this->artisan('reorder:generate')
            ->expectsOutput('Low-stock products found, but no reorder quantity to suggest.')
            ->assertSuccessful();

        $this->assertNull(PurchaseOrder::latest('id')->first());
    }

    public function test_generate_does_nothing_when_healthy(): void
    {
        $this->createProduct(['min_stock' => 10, 'max_stock' => 200, 'stock' => 100]);

        $this->artisan('reorder:generate')
            ->expectsOutput('No low-stock products.')
            ->assertSuccessful();

        $this->assertNull(PurchaseOrder::latest('id')->first());
    }
}
