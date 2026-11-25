<?php

namespace Tests\Feature\Kitchen;

use App\Models\Category;
use App\Models\DineArea;
use App\Models\DineBill;
use App\Models\DineOrder;
use App\Models\DineOrderItem;
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
use App\Services\DineOrderService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenDineOrderTicketTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private User $cashier;

    private Warehouse $warehouse;

    private Outlet $outlet;

    private DiningTable $table;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        $this->cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
        $this->cashier->markEmailAsVerified();
        $this->actingAs($this->cashier);

        $this->outlet = Outlet::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'is_active' => true, 'is_sales_enabled' => true]);
        $this->warehouse = Warehouse::create([
            'outlet_id' => $this->outlet->id,
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $area = DineArea::create(['name' => 'Indoor', 'outlet_id' => $this->outlet->id, 'is_active' => true, 'sort_order' => 0]);
        $this->table = DiningTable::create(['dine_area_id' => $area->id, 'name' => 'Meja 1', 'is_active' => true, 'capacity' => 4, 'sort_order' => 0]);
        $category = Category::create(['name' => 'Makanan', 'image' => 'categories/x.jpg', 'description' => 'Makanan']);
        $this->product = Product::create([
            'image' => 'products/test.jpg',
            'barcode' => 'KIT-'.++self::$seq,
            'sku' => 'KIT-'.self::$seq,
            'title' => 'Nasi Goreng',
            'description' => 'Test',
            'category_id' => $category->id,
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 20,
            'tax_rate' => 0,
        ]);
        $this->warehouse->products()->attach($this->product->id, ['stock' => 20]);
        $station = KitchenStation::create(['outlet_id' => $this->outlet->id, 'name' => 'Hot', 'sort_order' => 1, 'is_active' => true]);
        KitchenStationCategory::create([
            'kitchen_station_id' => $station->id,
            'category_id' => $category->id,
            'outlet_id' => $this->outlet->id,
        ]);
        app(CashierShiftService::class)->openShift($this->cashier, $this->cashier, 0, null, $this->warehouse->id);
    }

    private function submittedOrder(): DineOrder
    {
        $order = DineOrder::create([
            'dine_table_id' => $this->table->id,
            'status' => DineOrder::STATUS_SUBMITTED,
            'payment_option' => DineOrder::PAY_AT_COUNTER,
            'subtotal' => 20000,
            'item_count' => 2,
        ]);
        DineOrderItem::create([
            'dine_order_id' => $order->id,
            'product_id' => $this->product->id,
            'qty' => 2,
            'price' => 10000,
            'note' => 'Tanpa bawang',
        ]);

        return $order->fresh(['items']);
    }

    public function test_accept_creates_ticket_and_void_cancels_it(): void
    {
        $order = $this->submittedOrder();

        app(DineOrderService::class)->accept($order);

        $order->refresh();
        $ticket = KitchenTicket::where('dine_order_id', $order->id)->firstOrFail();
        $this->assertSame($this->outlet->id, $ticket->outlet_id);
        $this->assertSame('dine_in', $ticket->order_type);

        $ticketItem = KitchenTicketItem::where('dine_order_item_id', $order->items->first()->id)->firstOrFail();
        $this->assertSame('Nasi Goreng', $ticketItem->product_name);
        $this->assertSame('Tanpa bawang', $ticketItem->note);

        $bill = DineBill::where('dine_session_id', $order->dine_session_id)->firstOrFail();
        app(DineBillService::class)->voidItem($bill, $order->items->first(), 'Salah input', $this->cashier->id, $this->warehouse->id);

        $this->assertSame(KitchenTicketItem::STATUS_CANCELLED, $ticketItem->fresh()->status);
        $this->assertSame(KitchenTicket::STATUS_CANCELLED, $ticket->fresh()->status);
    }
}
