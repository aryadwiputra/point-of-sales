<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    public function test_can_import_products_and_attaches_to_warehouse(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $user->markEmailAsVerified();

        $outlet = Outlet::create([
            'code' => 'PST',
            'name' => 'Pusat',
            'is_active' => true,
            'is_sales_enabled' => false,
        ]);

        $warehouse = Warehouse::create([
            'outlet_id' => $outlet->id,
            'code' => 'WH-PST',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $csvContent = "barcode,sku,nama,deskripsi,kategori,harga_beli,harga_jual,stok,min_stok,max_stok,tipe_pajak,tarif_pajak\n";
        $csvContent .= "BRC-001,SKU-001,Kopi Arabika,Kopi enak,Minuman,15000,25000,50,5,100,exclusive,11\n";
        $csvContent .= "BRC-002,SKU-002,Teh Melati,Teh harum,Minuman,5000,10000,100,10,200,exclusive,11\n";

        $file = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $response = $this->actingAs($user)->post(route('import.products'), [
            'file' => $file,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('products', [
            'barcode' => 'BRC-001',
            'title' => 'Kopi Arabika',
            'stock' => 50,
        ]);
        $this->assertDatabaseHas('products', [
            'barcode' => 'BRC-002',
            'title' => 'Teh Melati',
            'stock' => 100,
        ]);

        $product1 = Product::where('barcode', 'BRC-001')->first();
        $this->assertNotNull($product1);
        $this->assertDatabaseHas('product_warehouse', [
            'product_id' => $product1->id,
            'warehouse_id' => $warehouse->id,
            'stock' => 50,
        ]);
    }

    public function test_can_reimport_and_update_existing_products(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $user->markEmailAsVerified();

        $category = Category::create(['name' => 'Snack']);
        Product::create([
            'barcode' => 'SNK-001',
            'sku' => 'SNK-001',
            'title' => 'Keripik Lama',
            'category_id' => $category->id,
            'buy_price' => 2000,
            'sell_price' => 5000,
            'stock' => 10,
        ]);

        $csvContent = "barcode,sku,nama,deskripsi,kategori,harga_beli,harga_jual,stok,min_stok,max_stok,tipe_pajak,tarif_pajak\n";
        $csvContent .= "SNK-001,SNK-001,Keripik Baru,,Snack,3000,6000,25,2,50,exclusive,11\n";

        $file = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $response = $this->actingAs($user)->post(route('import.products'), [
            'file' => $file,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('products', [
            'barcode' => 'SNK-001',
            'title' => 'Keripik Baru',
            'sell_price' => 6000,
            'stock' => 25,
        ]);
    }
}
