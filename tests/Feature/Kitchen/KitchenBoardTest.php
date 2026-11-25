<?php

namespace Tests\Feature\Kitchen;

use App\Models\KitchenStation;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Outlet;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenBoardTest extends TestCase
{
    use RefreshDatabase;

    private User $cook;

    private KitchenStation $station;

    private KitchenTicket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $outlet = Outlet::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'is_active' => true, 'is_sales_enabled' => true]);
        $this->station = KitchenStation::create(['outlet_id' => $outlet->id, 'name' => 'Hot', 'sort_order' => 1, 'is_active' => true]);

        $this->cook = User::factory()->create();
        $this->cook->assignRole('kitchen');
        $this->station->users()->attach($this->cook->id);

        $this->ticket = KitchenTicket::create([
            'kitchen_station_id' => $this->station->id,
            'outlet_id' => $outlet->id,
            'order_type' => 'dine_in',
            'status' => KitchenTicket::STATUS_OPEN,
            'placed_at' => now(),
        ]);
        KitchenTicketItem::create([
            'kitchen_ticket_id' => $this->ticket->id,
            'product_name' => 'Nasi Goreng',
            'qty' => 1,
            'status' => KitchenTicketItem::STATUS_QUEUED,
        ]);
    }

    public function test_kds_page_and_board_json(): void
    {
        $this->actingAs($this->cook)->get(route('kitchen.index'))->assertOk();

        $response = $this->actingAs($this->cook)->getJson(route('kitchen.board'));

        $response->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('tickets.0.id', $this->ticket->id)
            ->assertJsonPath('tickets.0.items.0.product_name', 'Nasi Goreng')
            ->assertJsonMissingPath('tickets.0.price');
    }

    public function test_item_status_action_returns_updated_ticket(): void
    {
        $item = $this->ticket->items()->firstOrFail();

        $this->actingAs($this->cook)
            ->patchJson(route('kitchen.items.status', $item), ['status' => KitchenTicketItem::STATUS_COOKING])
            ->assertOk()
            ->assertJsonPath('ticket.items.0.status', KitchenTicketItem::STATUS_COOKING);
    }

    public function test_unassigned_station_filter_is_forbidden(): void
    {
        $other = KitchenStation::create(['outlet_id' => $this->station->outlet_id, 'name' => 'Bar', 'sort_order' => 2, 'is_active' => true]);

        $this->actingAs($this->cook)
            ->getJson(route('kitchen.board', ['station' => $other->id]))
            ->assertForbidden();
    }

    public function test_manager_can_toggle_kitchen_enabled(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->actingAs($manager)
            ->post(route('kitchen.settings'), ['enabled' => false])
            ->assertRedirect();

        $this->assertFalse(Setting::getBool('kitchen_enabled', true));
        Setting::set('kitchen_enabled', '1');
    }

    public function test_kitchen_user_login_redirects_to_kds(): void
    {
        $user = User::factory()->create(['email' => 'cook@example.com', 'password' => bcrypt('password')]);
        $user->assignRole('kitchen');

        $this->post('/login', [
            'email' => 'cook@example.com',
            'password' => 'password',
        ] + $this->botGuardPayload())
            ->assertRedirect(route('kitchen.index', absolute: false));

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }
}
