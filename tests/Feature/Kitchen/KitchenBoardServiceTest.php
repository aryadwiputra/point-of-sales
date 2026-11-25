<?php

namespace Tests\Feature\Kitchen;

use App\Models\KitchenStation;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Outlet;
use App\Models\User;
use App\Services\KitchenTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class KitchenBoardServiceTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private KitchenStation $hot;

    private KitchenStation $bar;

    private User $cook;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['kitchen-access', 'kitchen-process', 'kitchen-manage'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $this->outlet = Outlet::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'is_active' => true, 'is_sales_enabled' => true]);
        $this->hot = KitchenStation::create(['outlet_id' => $this->outlet->id, 'name' => 'Hot', 'sort_order' => 1, 'is_active' => true]);
        $this->bar = KitchenStation::create(['outlet_id' => $this->outlet->id, 'name' => 'Bar', 'sort_order' => 2, 'is_active' => true]);

        $this->cook = User::factory()->create();
        $this->cook->givePermissionTo(['kitchen-access', 'kitchen-process']);
        $this->hot->users()->attach($this->cook->id);
    }

    private function ticket(KitchenStation $station, string $status = KitchenTicket::STATUS_OPEN): KitchenTicket
    {
        $ticket = KitchenTicket::create([
            'kitchen_station_id' => $station->id,
            'outlet_id' => $this->outlet->id,
            'order_type' => 'dine_in',
            'status' => $status,
            'placed_at' => now(),
        ]);

        KitchenTicketItem::create([
            'kitchen_ticket_id' => $ticket->id,
            'product_name' => 'Item',
            'qty' => 1,
            'status' => KitchenTicketItem::STATUS_QUEUED,
        ]);

        return $ticket;
    }

    public function test_board_filters_by_assignment_and_status(): void
    {
        $open = $this->ticket($this->hot);
        $this->ticket($this->bar);
        $this->ticket($this->hot, KitchenTicket::STATUS_CLOSED);

        $tickets = app(KitchenTicketService::class)->boardFor($this->cook);

        $this->assertCount(1, $tickets);
        $this->assertSame($open->id, $tickets->first()->id);
        $this->assertCount(1, app(KitchenTicketService::class)->stationsFor($this->cook));
    }

    public function test_manager_can_access_all_outlet_stations(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo(['kitchen-access', 'kitchen-manage']);
        $manager->outlets()->attach($this->outlet->id);

        $service = app(KitchenTicketService::class);
        $this->assertTrue($service->userCanAccessStation($manager, $this->bar));
        $this->assertCount(2, $service->stationsFor($manager));
    }

    public function test_other_kitchen_user_cannot_select_station(): void
    {
        $other = User::factory()->create();
        $other->givePermissionTo(['kitchen-access', 'kitchen-process']);

        $service = app(KitchenTicketService::class);
        $this->assertFalse($service->userCanAccessStation($other, $this->hot));

        $this->expectException(HttpException::class);
        $service->boardFor($other, $this->hot->id);
    }
}
