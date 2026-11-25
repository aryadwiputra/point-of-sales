<?php

namespace Tests\Feature\DineIn;

use App\Models\DineArea;
use App\Models\DineSession;
use App\Models\DiningTable;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DineSessionService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DineSessionRoutesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private DiningTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        $this->admin = User::where('email', 'arya@gmail.com')->first();
        $this->admin->markEmailAsVerified();
        Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $area = DineArea::create(['name' => 'Indoor', 'is_active' => true]);
        $this->table = DiningTable::create([
            'dine_area_id' => $area->id,
            'name' => 'Meja Route',
            'is_active' => true,
            'capacity' => 4,
        ]);
    }

    public function test_open_and_close_session_routes(): void
    {
        $this->actingAs($this->admin)
            ->post(route('dine-sessions.store', $this->table), ['guest_count' => 2])
            ->assertSessionHasNoErrors();

        $session = DineSession::firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('dine-sessions.close', $session))
            ->assertSessionHasNoErrors();

        $this->assertSame(DineSession::STATUS_CLOSED, $session->fresh()->status);
    }

    public function test_move_and_merge_routes(): void
    {
        $target = DiningTable::create([
            'name' => 'Meja Tujuan',
            'is_active' => true,
            'capacity' => 4,
        ]);
        $other = DiningTable::create([
            'name' => 'Meja Lain',
            'is_active' => true,
            'capacity' => 4,
        ]);
        $service = app(DineSessionService::class);
        $session = $service->open($this->table, 2, null, $this->admin->id);
        $targetSession = $service->open($other, 2, null, $this->admin->id);

        $this->actingAs($this->admin)
            ->post(route('dine-sessions.move', $session), ['dine_table_id' => $target->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($target->id, $session->fresh()->dine_table_id);

        $this->actingAs($this->admin)
            ->post(route('dine-sessions.merge', $session), ['target_session_id' => $targetSession->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(DineSession::STATUS_CLOSED, $session->fresh()->status);
    }

    public function test_user_without_permission_gets_403(): void
    {
        $user = User::factory()->create();
        $user->markEmailAsVerified();

        $this->actingAs($user)
            ->post(route('dine-sessions.store', $this->table), [])
            ->assertForbidden();
    }

    public function test_delete_table_with_open_session_is_blocked(): void
    {
        app(DineSessionService::class)->open($this->table, 2, null, $this->admin->id);

        $this->actingAs($this->admin)
            ->delete(route('dine-tables.destroy', $this->table))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('dine_tables', ['id' => $this->table->id]);
    }
}
