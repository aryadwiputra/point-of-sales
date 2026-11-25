<?php

namespace Tests\Feature\Kitchen;

use App\Models\Category;
use App\Models\KitchenStation;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KitchenSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_kitchen_tables_and_columns_exist(): void
    {
        foreach ([
            'kitchen_stations',
            'kitchen_station_categories',
            'product_kitchen_station_overrides',
            'kitchen_station_user',
            'kitchen_tickets',
            'kitchen_ticket_items',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
        }

        foreach ([
            'kitchen_stations' => ['outlet_id', 'name', 'sort_order', 'is_active'],
            'kitchen_tickets' => ['kitchen_station_id', 'outlet_id', 'dine_order_id', 'transaction_id', 'status', 'order_type', 'placed_at', 'ready_at', 'closed_at'],
            'kitchen_ticket_items' => ['kitchen_ticket_id', 'dine_order_item_id', 'transaction_detail_id', 'product_name', 'qty', 'modifiers_text', 'note', 'status', 'cancel_reason', 'started_at', 'ready_at', 'cancelled_at'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "Missing {$table}.{$column}");
            }
        }
    }

    public function test_kitchen_models_relate_and_cast(): void
    {
        $outlet = Outlet::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'is_active' => true, 'is_sales_enabled' => true]);
        $category = Category::create(['name' => 'Minuman', 'image' => 'categories/test.jpg', 'description' => 'Minuman']);
        $station = KitchenStation::create([
            'outlet_id' => $outlet->id,
            'name' => 'Bar',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        $station->categories()->attach($category->id, ['outlet_id' => $outlet->id]);
        $station->users()->attach($user->id);

        $this->assertTrue($station->categories()->whereKey($category->id)->exists());
        $this->assertTrue($station->users()->whereKey($user->id)->exists());
        $this->assertTrue($user->kitchenStations()->whereKey($station->id)->exists());

        $ticket = KitchenTicket::create([
            'kitchen_station_id' => $station->id,
            'outlet_id' => $outlet->id,
            'order_type' => 'dine_in',
            'status' => KitchenTicket::STATUS_OPEN,
            'placed_at' => now(),
        ]);

        $item = KitchenTicketItem::create([
            'kitchen_ticket_id' => $ticket->id,
            'product_name' => 'Kopi Susu',
            'qty' => 2,
            'status' => KitchenTicketItem::STATUS_QUEUED,
        ]);

        $this->assertSame($station->id, $ticket->station->id);
        $this->assertSame($ticket->id, $item->ticket->id);
        $this->assertTrue($item->isActive());
        $this->assertSame('queued', $item->status);
        $this->assertSame(2, $item->qty);
    }
}
