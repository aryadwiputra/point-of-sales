<?php

namespace Tests\Feature\Inventory;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OutletAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OutletAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_outlet_installation_keeps_unassigned_user_compatible(): void
    {
        $outlet = Outlet::create(['code' => 'PUSAT', 'name' => 'Pusat']);
        $warehouse = Warehouse::create([
            'code' => 'PUSAT', 'name' => 'Gudang Pusat', 'type' => 'main',
            'is_active' => true, 'sort_order' => 0, 'outlet_id' => $outlet->id,
        ]);

        $this->assertTrue(app(OutletAccessService::class)->canUseWarehouse(User::factory()->create(), $warehouse));
    }

    public function test_user_can_only_use_assigned_outlet_warehouses(): void
    {
        $allowed = Outlet::create(['code' => 'MAL', 'name' => 'Malabar']);
        $blocked = Outlet::create(['code' => 'PUT', 'name' => 'Puter']);
        $allowedWarehouse = Warehouse::create([
            'code' => 'MAL', 'name' => 'Malabar', 'type' => 'branch',
            'is_active' => true, 'sort_order' => 0, 'outlet_id' => $allowed->id,
        ]);
        $blockedWarehouse = Warehouse::create([
            'code' => 'PUT', 'name' => 'Puter', 'type' => 'branch',
            'is_active' => true, 'sort_order' => 1, 'outlet_id' => $blocked->id,
        ]);
        $user = User::factory()->create();
        $user->outlets()->attach($allowed->id, ['is_default' => true]);

        $service = app(OutletAccessService::class);
        $this->assertTrue($service->canUseWarehouse($user, $allowedWarehouse));
        $this->assertFalse($service->canUseWarehouse($user, $blockedWarehouse));
        $this->assertSame([$allowedWarehouse->id], $service->warehousesFor($user)->pluck('id')->all());
    }

    public function test_multi_outlet_stock_mutation_index_hides_warehouse_less_rows(): void
    {
        $allowed = Outlet::create(['code' => 'MAL', 'name' => 'Malabar']);
        $blocked = Outlet::create(['code' => 'PUT', 'name' => 'Puter']);
        $allowedWarehouse = Warehouse::create([
            'code' => 'MAL', 'name' => 'Malabar', 'type' => 'branch',
            'is_active' => true, 'sort_order' => 0, 'outlet_id' => $allowed->id,
        ]);
        $blockedWarehouse = Warehouse::create([
            'code' => 'PUT', 'name' => 'Puter', 'type' => 'branch',
            'is_active' => true, 'sort_order' => 1, 'outlet_id' => $blocked->id,
        ]);
        $permission = Permission::firstOrCreate([
            'name' => 'stock-mutations-access',
            'guard_name' => 'web',
        ]);
        $user = User::factory()->create();
        $user->markEmailAsVerified();
        $user->givePermissionTo($permission);
        $user->outlets()->attach($allowed->id, ['is_default' => true]);
        $category = \App\Models\Category::create([
            'name' => 'Kategori Mutasi',
            'image' => 'categories/mutation.jpg',
            'description' => 'Kategori mutasi',
        ]);
        $product = Product::create([
            'title' => 'Produk Mutasi',
            'description' => 'Produk pengujian',
            'category_id' => $category->id,
            'image' => 'products/mutation.jpg',
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'barcode' => 'BC-'.Str::upper(Str::random(8)),
            'buy_price' => 1000,
            'sell_price' => 1500,
            'stock' => 0,
            'tax_rate' => 0,
        ]);

        StockMutation::create([
            'product_id' => $product->id,
            'warehouse_id' => $allowedWarehouse->id,
            'reference_type' => 'test',
            'mutation_type' => 'in',
            'qty' => 1,
            'stock_before' => 0,
            'stock_after' => 1,
        ]);
        StockMutation::create([
            'product_id' => $product->id,
            'warehouse_id' => $blockedWarehouse->id,
            'reference_type' => 'test',
            'mutation_type' => 'in',
            'qty' => 1,
            'stock_before' => 0,
            'stock_after' => 1,
        ]);
        StockMutation::create([
            'product_id' => $product->id,
            'warehouse_id' => null,
            'reference_type' => 'legacy_import',
            'mutation_type' => 'in',
            'qty' => 1,
            'stock_before' => 0,
            'stock_after' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('stock-mutations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/StockMutations/Index')
                ->where('stockMutations.data', fn ($rows) => $rows->count() === 1
                    && $rows->first()['warehouse_id'] === $allowedWarehouse->id));
    }
}
