<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use App\Models\Category;

class WarehouseTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        $this->admin = User::where('email', 'arya@gmail.com')->first();
        $this->admin->markEmailAsVerified();
        $this->actingAs($this->admin);
        $this->withSession($this->recentlyConfirmedSession());

        // Seed default warehouse
        $pusat = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        // Create sample product + pivot
        $category = Category::create(['image' => 'test.png', 'name' => 'Test', 'description' => 'Test']);
        $product = Product::create([
            'category_id' => $category->id,
            'image' => 'product.png',
            'barcode' => 'TEST001',
            'title' => 'Test Product',
            'description' => 'Test',
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 100,
        ]);
        $pusat->products()->attach($product->id, ['stock' => 100]);
    }

    public function test_index_warehouses_requires_warehouses_access_permission()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('warehouses-access');

        $this->actingAs($user)
            ->get(route('settings.warehouses.index'))
            ->assertOk();
    }

    public function test_index_warehouses_denies_without_permission()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.warehouses.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_warehouse()
    {
        $response = $this->from(route('settings.warehouses.index'))
            ->post(route('settings.warehouses.store'), [
                'code' => 'CABANG-A',
                'name' => 'Cabang A',
                'type' => 'branch',
                'address' => 'Jl. Merdeka No. 1',
                'phone' => '021-123456',
                'is_active' => true,
                'sort_order' => 1,
            ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('warehouses', ['code' => 'CABANG-A']);
    }

    public function test_create_warehouse_syncs_all_products()
    {
        $productCount = Product::count();

        $this->from(route('settings.warehouses.index'))
            ->post(route('settings.warehouses.store'), [
                'code' => 'CABANG-B',
                'name' => 'Cabang B',
                'type' => 'branch',
                'sort_order' => 2,
            ]);

        $warehouse = Warehouse::where('code', 'CABANG-B')->first();
        $this->assertNotNull($warehouse);
        $this->assertEquals($productCount, $warehouse->products()->count());
    }

    public function test_admin_can_update_warehouse()
    {
        $warehouse = Warehouse::factory()->create(['code' => 'GUDANG-1', 'name' => 'Gudang 1']);

        $this->from(route('settings.warehouses.index'))
            ->put(route('settings.warehouses.update', $warehouse->id), [
                'code' => 'GUDANG-1',
                'name' => 'Gudang 1 Updated',
                'type' => 'warehouse',
                'sort_order' => 0,
                'is_active' => true,
            ])->assertSessionHas('success');

        $this->assertDatabaseHas('warehouses', ['name' => 'Gudang 1 Updated']);
    }

    public function test_cannot_delete_main_warehouse()
    {
        $main = Warehouse::where('type', 'main')->first();

        $this->from(route('settings.warehouses.index'))
            ->delete(route('settings.warehouses.destroy', $main->id))
            ->assertSessionHas('error');
    }

    public function test_cannot_delete_warehouse_with_stock()
    {
        $warehouse = Warehouse::factory()->create(['code' => 'HAS-STOK', 'type' => 'branch']);
        $product = Product::first();
        $warehouse->products()->attach($product->id, ['stock' => 10]);

        $this->from(route('settings.warehouses.index'))
            ->delete(route('settings.warehouses.destroy', $warehouse->id))
            ->assertSessionHas('error');
    }

    public function test_can_delete_empty_warehouse()
    {
        $warehouse = Warehouse::factory()->create(['code' => 'KOSONG', 'type' => 'branch']);

        $this->from(route('settings.warehouses.index'))
            ->delete(route('settings.warehouses.destroy', $warehouse->id))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('warehouses', ['id' => $warehouse->id]);
    }

    public function test_default_warehouse_is_created_after_seed()
    {
        $this->assertDatabaseHas('warehouses', ['code' => 'PUSAT', 'type' => 'main']);
    }

    public function test_product_has_stock_total_helper()
    {
        $warehouse = Warehouse::where('code', 'PUSAT')->first();
        $product = Product::first();
        $product->warehouses()->syncWithPivotValues([$warehouse->id], ['stock' => 50]);

        $this->assertEquals(50, $product->stockTotal());
    }

    public function test_code_must_be_unique()
    {
        Warehouse::factory()->create(['code' => 'DUPLICATE']);

        $this->post(route('settings.warehouses.store'), [
            'code' => 'DUPLICATE',
            'name' => 'Duplicate',
            'type' => 'branch',
            'sort_order' => 0,
        ])->assertInvalid(['code']);
    }
}
