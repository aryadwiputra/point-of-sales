<?php

namespace Tests\Feature\Kitchen;

use App\Models\Category;
use App\Models\KitchenStation;
use App\Models\KitchenStationCategory;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductKitchenStationOverride;
use App\Services\KitchenTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenRoutingTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function product(Category $category): Product
    {
        self::$seq++;

        return Product::create([
            'image' => 'products/test.jpg',
            'barcode' => 'KIT-'.self::$seq,
            'sku' => 'KIT-'.self::$seq,
            'title' => 'Produk '.self::$seq,
            'description' => 'Test',
            'category_id' => $category->id,
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 10,
            'tax_rate' => 0,
        ]);
    }

    public function test_category_mapping_routes_and_override_wins(): void
    {
        $outlet = Outlet::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'is_active' => true, 'is_sales_enabled' => true]);
        $category = Category::create(['name' => 'Makanan', 'image' => 'categories/x.jpg', 'description' => 'Makanan']);
        $product = $this->product($category);

        $kitchen = KitchenStation::create(['outlet_id' => $outlet->id, 'name' => 'Hot', 'sort_order' => 1, 'is_active' => true]);
        $bar = KitchenStation::create(['outlet_id' => $outlet->id, 'name' => 'Bar', 'sort_order' => 2, 'is_active' => true]);

        KitchenStationCategory::create([
            'kitchen_station_id' => $kitchen->id,
            'category_id' => $category->id,
            'outlet_id' => $outlet->id,
        ]);

        $service = app(KitchenTicketService::class);
        $this->assertSame($kitchen->id, $service->routeFor($product, $outlet->id)?->id);

        ProductKitchenStationOverride::create([
            'product_id' => $product->id,
            'kitchen_station_id' => $bar->id,
            'outlet_id' => $outlet->id,
        ]);

        $this->assertSame($bar->id, $service->routeFor($product, $outlet->id)?->id);
    }

    public function test_inactive_station_and_other_outlet_do_not_route(): void
    {
        $outlet = Outlet::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'is_active' => true, 'is_sales_enabled' => true]);
        $other = Outlet::create(['code' => 'OUT-B', 'name' => 'Outlet B', 'is_active' => true, 'is_sales_enabled' => true]);
        $category = Category::create(['name' => 'Makanan', 'image' => 'categories/x.jpg', 'description' => 'Makanan']);
        $product = $this->product($category);
        $station = KitchenStation::create(['outlet_id' => $outlet->id, 'name' => 'Hot', 'sort_order' => 1, 'is_active' => true]);

        KitchenStationCategory::create([
            'kitchen_station_id' => $station->id,
            'category_id' => $category->id,
            'outlet_id' => $outlet->id,
        ]);

        $service = app(KitchenTicketService::class);
        $this->assertNull($service->routeFor($product, $other->id));

        $station->update(['is_active' => false]);
        $this->assertNull($service->routeFor($product, $outlet->id));
    }

    public function test_null_outlet_matches_only_null_routing(): void
    {
        $outlet = Outlet::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'is_active' => true, 'is_sales_enabled' => true]);
        $category = Category::create(['name' => 'Makanan', 'image' => 'categories/x.jpg', 'description' => 'Makanan']);
        $product = $this->product($category);
        $station = KitchenStation::create(['outlet_id' => null, 'name' => 'Legacy', 'sort_order' => 1, 'is_active' => true]);

        KitchenStationCategory::create([
            'kitchen_station_id' => $station->id,
            'category_id' => $category->id,
            'outlet_id' => null,
        ]);

        $service = app(KitchenTicketService::class);
        $this->assertNull($service->routeFor($product, $outlet->id));
        $this->assertSame($station->id, $service->routeFor($product, null)?->id);
    }
}
