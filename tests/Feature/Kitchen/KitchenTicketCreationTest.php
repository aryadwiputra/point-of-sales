<?php

namespace Tests\Feature\Kitchen;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\DineOrder;
use App\Models\DineOrderItem;
use App\Models\DineOrderItemModifier;
use App\Models\DiningTable;
use App\Models\KitchenStation;
use App\Models\KitchenStationCategory;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Setting;
use App\Services\KitchenTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenTicketCreationTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private Outlet $outlet;

    private Category $category;

    private KitchenStation $station;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outlet = Outlet::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'is_active' => true, 'is_sales_enabled' => true]);
        $this->category = Category::create(['name' => 'Makanan', 'image' => 'categories/x.jpg', 'description' => 'Makanan']);
        $this->station = KitchenStation::create(['outlet_id' => $this->outlet->id, 'name' => 'Hot', 'sort_order' => 1, 'is_active' => true]);
        KitchenStationCategory::create([
            'kitchen_station_id' => $this->station->id,
            'category_id' => $this->category->id,
            'outlet_id' => $this->outlet->id,
        ]);

        self::$seq++;
        $this->product = Product::create([
            'image' => 'products/test.jpg',
            'barcode' => 'KIT-'.self::$seq,
            'sku' => 'KIT-'.self::$seq,
            'title' => 'Nasi Goreng',
            'description' => 'Test',
            'category_id' => $this->category->id,
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 10,
            'tax_rate' => 0,
        ]);
    }

    private function dineOrder(Product $product, string $note = 'Tanpa bawang'): DineOrder
    {
        $table = DiningTable::create(['name' => 'Meja 1', 'is_active' => true, 'capacity' => 4, 'sort_order' => 0]);
        $order = DineOrder::create([
            'dine_table_id' => $table->id,
            'status' => DineOrder::STATUS_SUBMITTED,
            'payment_option' => DineOrder::PAY_AT_COUNTER,
            'subtotal' => 20000,
            'item_count' => 2,
        ]);

        $item = DineOrderItem::create([
            'dine_order_id' => $order->id,
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 10000,
            'note' => $note,
        ]);

        DineOrderItemModifier::create([
            'dine_order_item_id' => $item->id,
            'modifier_id' => null,
            'modifier_group_id' => null,
            'group_name' => 'Pedas',
            'name' => 'Extra Pedas',
            'price_delta' => 0,
        ]);

        return $order->fresh(['items.product', 'items.modifiers']);
    }

    public function test_dine_order_creates_one_ticket_per_station_with_snapshots(): void
    {
        $order = $this->dineOrder($this->product);

        app(KitchenTicketService::class)->createForDineOrder($order, $this->outlet->id);

        $ticket = KitchenTicket::where('dine_order_id', $order->id)->firstOrFail();
        $this->assertSame($this->station->id, $ticket->kitchen_station_id);
        $this->assertSame('dine_in', $ticket->order_type);
        $this->assertSame(KitchenTicket::STATUS_OPEN, $ticket->status);

        $item = KitchenTicketItem::where('dine_order_item_id', $order->items->first()->id)->firstOrFail();
        $this->assertSame('Nasi Goreng', $item->product_name);
        $this->assertSame(2, $item->qty);
        $this->assertSame('Extra Pedas', $item->modifiers_text);
        $this->assertSame('Tanpa bawang', $item->note);
        $this->assertSame(KitchenTicketItem::STATUS_QUEUED, $item->status);
    }

    public function test_creation_is_idempotent_and_skips_unmapped_products(): void
    {
        $order = $this->dineOrder($this->product);
        $service = app(KitchenTicketService::class);

        $service->createForDineOrder($order, $this->outlet->id);
        $service->createForDineOrder($order, $this->outlet->id);

        $this->assertSame(1, KitchenTicket::where('dine_order_id', $order->id)->count());
        $this->assertSame(1, KitchenTicketItem::where('dine_order_item_id', $order->items->first()->id)->count());

        $otherCategory = Category::create(['name' => 'Lainnya', 'image' => 'categories/y.jpg', 'description' => 'Lainnya']);
        self::$seq++;
        $otherProduct = Product::create([
            'image' => 'products/test.jpg',
            'barcode' => 'KIT-'.self::$seq,
            'sku' => 'KIT-'.self::$seq,
            'title' => 'Produk Tanpa Station',
            'description' => 'Test',
            'category_id' => $otherCategory->id,
            'buy_price' => 1000,
            'sell_price' => 2000,
            'stock' => 10,
            'tax_rate' => 0,
        ]);

        $otherOrder = $this->dineOrder($otherProduct);
        $service->createForDineOrder($otherOrder, $this->outlet->id);

        $this->assertSame(0, KitchenTicket::where('dine_order_id', $otherOrder->id)->count());
        $this->assertTrue(AuditLog::where('event', 'kitchen.ticket_skipped')->exists());
    }

    public function test_disabled_kitchen_creates_no_ticket(): void
    {
        Setting::set('kitchen_enabled', '0');
        $order = $this->dineOrder($this->product);

        app(KitchenTicketService::class)->createForDineOrder($order, $this->outlet->id);

        $this->assertSame(0, KitchenTicket::where('dine_order_id', $order->id)->count());
        Setting::set('kitchen_enabled', '1');
    }
}
