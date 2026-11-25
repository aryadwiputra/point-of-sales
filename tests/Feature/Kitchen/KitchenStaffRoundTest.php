<?php

namespace Tests\Feature\Kitchen;

use App\Models\Cart;
use App\Models\Category;
use App\Models\DineArea;
use App\Models\DiningTable;
use App\Models\KitchenStation;
use App\Models\KitchenStationCategory;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use App\Services\DineBillService;
use App\Services\DineSessionService;
use App\Services\DineStaffOrderService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenStaffRoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_round_creates_ticket(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        $cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
        $cashier->markEmailAsVerified();
        $this->actingAs($cashier);

        $outlet = Outlet::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'is_active' => true, 'is_sales_enabled' => true]);
        $warehouse = Warehouse::create([
            'outlet_id' => $outlet->id,
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $area = DineArea::create(['name' => 'Indoor', 'outlet_id' => $outlet->id, 'is_active' => true, 'sort_order' => 0]);
        $table = DiningTable::create(['dine_area_id' => $area->id, 'name' => 'Meja 2', 'is_active' => true, 'capacity' => 4, 'sort_order' => 0]);
        $category = Category::create(['name' => 'Makanan', 'image' => 'categories/x.jpg', 'description' => 'Makanan']);
        $product = Product::create([
            'image' => 'products/test.jpg',
            'barcode' => 'KIT-SR-1',
            'sku' => 'KIT-SR-1',
            'title' => 'Mie Goreng',
            'description' => 'Test',
            'category_id' => $category->id,
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 20,
            'tax_rate' => 0,
        ]);
        $warehouse->products()->attach($product->id, ['stock' => 20]);
        $station = KitchenStation::create(['outlet_id' => $outlet->id, 'name' => 'Hot', 'sort_order' => 1, 'is_active' => true]);
        KitchenStationCategory::create([
            'kitchen_station_id' => $station->id,
            'category_id' => $category->id,
            'outlet_id' => $outlet->id,
        ]);
        app(CashierShiftService::class)->openShift($cashier, $cashier, 0, null, $warehouse->id);

        $session = app(DineSessionService::class)->open($table, 2, null, $cashier->id);
        $bill = app(DineBillService::class)->openBillForSession($session, $cashier->id);

        Cart::create([
            'cashier_id' => $cashier->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => 10000,
            'note' => 'Pedas',
        ]);

        app(DineStaffOrderService::class)->submit($cashier->id, $bill);

        $ticket = KitchenTicket::firstOrFail();
        $this->assertSame($station->id, $ticket->kitchen_station_id);
        $this->assertSame('Pedas', KitchenTicketItem::firstOrFail()->note);
    }
}
