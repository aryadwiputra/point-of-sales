<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReconcileCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

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
    }

    private static int $seq = 0;

    private function createProduct(int $globalStock, int $pivotStock): Product
    {
        self::$seq++;

        $product = Product::create([
            'title' => 'Produk '.self::$seq,
            'sku' => 'SKU-REC-'.self::$seq,
            'barcode' => 'BC-REC-'.self::$seq,
            'image' => 'products/test.jpg',
            'description' => 'Deskripsi produk test',
            'category_id' => $this->category->id,
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => $globalStock,
            'tax_rate' => 0,
        ]);

        $product->warehouses()->attach($this->warehouse->id, ['stock' => $pivotStock]);

        return $product;
    }

    public function test_report_identifies_mismatch_between_global_and_pivot_stock(): void
    {
        $this->createProduct(100, 60);

        $this->artisan('inventory:reconcile')
            ->expectsOutputToContain('products.stock differs')
            ->expectsOutputToContain('100')
            ->assertSuccessful();

        // report mode is read-only
        $this->assertEquals(100, Product::latest('id')->first()->stock);
    }

    public function test_report_is_silent_when_consistent(): void
    {
        $this->createProduct(60, 60);

        $this->artisan('inventory:reconcile')
            ->expectsOutputToContain('Inventory is consistent')
            ->assertSuccessful();
    }

    public function test_fix_aligns_global_stock_to_pivot_total(): void
    {
        $this->createProduct(100, 60);

        $this->artisan('inventory:reconcile', ['--fix' => true])
            ->assertSuccessful();

        $this->assertEquals(60, Product::latest('id')->first()->stock);
    }
}
