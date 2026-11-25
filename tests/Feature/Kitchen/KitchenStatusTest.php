<?php

namespace Tests\Feature\Kitchen;

use App\Models\Category;
use App\Models\DineOrder;
use App\Models\DineOrderItem;
use App\Models\DiningTable;
use App\Models\KitchenStation;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Services\KitchenTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KitchenStatusTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private KitchenStation $station;

    private User $cook;

    private KitchenTicket $ticket;

    private KitchenTicketItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['kitchen-access', 'kitchen-process', 'kitchen-manage'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $this->outlet = Outlet::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'is_active' => true, 'is_sales_enabled' => true]);
        $this->station = KitchenStation::create(['outlet_id' => $this->outlet->id, 'name' => 'Hot', 'sort_order' => 1, 'is_active' => true]);

        $this->cook = User::factory()->create();
        $this->cook->givePermissionTo(['kitchen-access', 'kitchen-process']);
        $this->station->users()->attach($this->cook->id);

        $this->ticket = KitchenTicket::create([
            'kitchen_station_id' => $this->station->id,
            'outlet_id' => $this->outlet->id,
            'order_type' => 'dine_in',
            'status' => KitchenTicket::STATUS_OPEN,
            'placed_at' => now(),
        ]);

        $this->item = KitchenTicketItem::create([
            'kitchen_ticket_id' => $this->ticket->id,
            'product_name' => 'Nasi Goreng',
            'qty' => 1,
            'status' => KitchenTicketItem::STATUS_QUEUED,
        ]);
    }

    public function test_item_moves_forward_and_back_with_ticket_derivation(): void
    {
        $service = app(KitchenTicketService::class);

        $item = $service->updateItemStatus($this->item, KitchenTicketItem::STATUS_COOKING, $this->cook);
        $this->assertSame('cooking', $item->status);
        $this->assertNotNull($item->started_at);
        $this->assertSame(KitchenTicket::STATUS_OPEN, $this->ticket->fresh()->status);

        $item = $service->updateItemStatus($item, KitchenTicketItem::STATUS_READY, $this->cook);
        $this->assertSame('ready', $item->status);
        $this->assertSame(KitchenTicket::STATUS_READY, $this->ticket->fresh()->status);
        $this->assertNotNull($this->ticket->fresh()->ready_at);

        $item = $service->updateItemStatus($item, KitchenTicketItem::STATUS_COOKING, $this->cook);
        $this->assertNull($item->ready_at);
        $this->assertSame(KitchenTicket::STATUS_OPEN, $this->ticket->fresh()->status);
        $this->assertNull($this->ticket->fresh()->ready_at);
    }

    public function test_invalid_transition_and_closed_ticket_are_rejected(): void
    {
        $service = app(KitchenTicketService::class);

        $this->expectException(ValidationException::class);
        $service->updateItemStatus($this->item, KitchenTicketItem::STATUS_READY, $this->cook);
    }

    public function test_bulk_actions_and_clear_require_ready(): void
    {
        $service = app(KitchenTicketService::class);

        $this->expectException(ValidationException::class);
        $service->clearTicket($this->ticket, $this->cook);
    }

    public function test_bulk_ready_then_clear_closes_ticket(): void
    {
        $service = app(KitchenTicketService::class);

        $ticket = $service->markTicketReady($this->ticket, $this->cook);
        $this->assertSame(KitchenTicket::STATUS_READY, $ticket->status);
        $this->assertSame(KitchenTicketItem::STATUS_READY, $ticket->items()->first()->status);

        $ticket = $service->clearTicket($ticket, $this->cook);
        $this->assertSame(KitchenTicket::STATUS_CLOSED, $ticket->status);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_cancelling_after_clear_keeps_closed_until_all_items_cancelled(): void
    {
        $service = app(KitchenTicketService::class);

        $category = Category::create(['name' => 'Makanan', 'image' => 'categories/x.jpg', 'description' => 'Makanan']);
        $product = Product::create([
            'image' => 'products/test.jpg',
            'barcode' => uniqid('KIT-'),
            'sku' => uniqid('KIT-'),
            'title' => 'Nasi Goreng',
            'description' => 'Test',
            'category_id' => $category->id,
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 10,
            'tax_rate' => 0,
        ]);
        $table = DiningTable::create(['name' => 'Meja 1', 'is_active' => true, 'capacity' => 4, 'sort_order' => 0]);
        $order = DineOrder::create([
            'dine_table_id' => $table->id,
            'status' => DineOrder::STATUS_SUBMITTED,
            'payment_option' => DineOrder::PAY_AT_COUNTER,
            'subtotal' => 20000,
            'item_count' => 2,
        ]);

        $firstSource = DineOrderItem::create([
            'dine_order_id' => $order->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => 10000,
        ]);
        $secondSource = DineOrderItem::create([
            'dine_order_id' => $order->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => 10000,
        ]);
        $this->item->update(['dine_order_item_id' => $firstSource->id]);

        $second = KitchenTicketItem::create([
            'kitchen_ticket_id' => $this->ticket->id,
            'dine_order_item_id' => $secondSource->id,
            'product_name' => 'Es Teh',
            'qty' => 1,
            'status' => KitchenTicketItem::STATUS_QUEUED,
        ]);

        $ticket = $service->markTicketReady($this->ticket, $this->cook);
        $ticket = $service->clearTicket($ticket, $this->cook);

        $service->cancelForDineOrderItem($firstSource, 'Salah input', $this->cook->id);
        $this->assertSame(KitchenTicket::STATUS_CLOSED, $ticket->fresh()->status);

        $service->cancelForDineOrderItem($secondSource, 'Salah input', $this->cook->id);
        $this->assertSame(KitchenTicket::STATUS_CANCELLED, $ticket->fresh()->status);
    }
}
