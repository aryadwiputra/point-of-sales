<?php

namespace Tests\Feature\Kitchen;

use App\Models\Category;
use App\Models\KitchenStation;
use App\Models\KitchenTicket;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenStationAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Outlet $outlet;

    private Category $category;

    private Product $product;

    private User $cook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        $this->admin = User::where('email', 'arya@gmail.com')->firstOrFail();
        $this->admin->markEmailAsVerified();
        $this->actingAs($this->admin);

        $this->outlet = Outlet::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'is_active' => true, 'is_sales_enabled' => true]);
        $this->category = Category::create(['name' => 'Makanan', 'image' => 'categories/x.jpg', 'description' => 'Makanan']);
        $this->product = Product::create([
            'image' => 'products/test.jpg',
            'barcode' => 'KIT-ADM-1',
            'sku' => 'KIT-ADM-1',
            'title' => 'Nasi Goreng',
            'description' => 'Test',
            'category_id' => $this->category->id,
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 10,
            'tax_rate' => 0,
        ]);
        $this->cook = User::factory()->create();
        $this->cook->assignRole('kitchen');
    }

    public function test_admin_creates_station_with_mappings_and_overrides(): void
    {
        $this->post(route('kitchen.stations.store'), [
            'outlet_id' => $this->outlet->id,
            'name' => 'Hot Kitchen',
            'sort_order' => 1,
            'is_active' => true,
            'category_ids' => [$this->category->id],
            'user_ids' => [$this->cook->id],
            'product_ids' => [$this->product->id],
        ])->assertRedirect();

        $station = KitchenStation::where('name', 'Hot Kitchen')->firstOrFail();

        $this->assertDatabaseHas('kitchen_station_categories', [
            'kitchen_station_id' => $station->id,
            'category_id' => $this->category->id,
            'outlet_id' => $this->outlet->id,
        ]);
        $this->assertDatabaseHas('kitchen_station_user', [
            'kitchen_station_id' => $station->id,
            'user_id' => $this->cook->id,
        ]);
        $this->assertDatabaseHas('product_kitchen_station_overrides', [
            'product_id' => $this->product->id,
            'kitchen_station_id' => $station->id,
            'outlet_id' => $this->outlet->id,
        ]);
    }

    public function test_category_cannot_be_mapped_to_two_stations_in_same_outlet(): void
    {
        $this->post(route('kitchen.stations.store'), [
            'outlet_id' => $this->outlet->id,
            'name' => 'Hot Kitchen',
            'category_ids' => [$this->category->id],
        ])->assertRedirect();

        $this->post(route('kitchen.stations.store'), [
            'outlet_id' => $this->outlet->id,
            'name' => 'Second Kitchen',
            'category_ids' => [$this->category->id],
        ])->assertSessionHasErrors('category_ids');
    }

    public function test_station_with_tickets_cannot_be_deleted(): void
    {
        $station = KitchenStation::create(['outlet_id' => $this->outlet->id, 'name' => 'Hot', 'sort_order' => 1, 'is_active' => true]);
        KitchenTicket::create([
            'kitchen_station_id' => $station->id,
            'outlet_id' => $this->outlet->id,
            'order_type' => 'takeaway',
            'status' => KitchenTicket::STATUS_OPEN,
            'placed_at' => now(),
        ]);

        $this->delete(route('kitchen.stations.destroy', $station))->assertSessionHas('error');
        $this->assertDatabaseHas('kitchen_stations', ['id' => $station->id]);
    }
}
