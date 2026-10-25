<?php

namespace Tests\Feature\DineIn;

use App\Models\DineArea;
use App\Models\DiningTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DineTableManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->markEmailAsVerified();
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => 'dine-tables-access',
            'guard_name' => 'web',
        ]));
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => 'dine-tables-create',
            'guard_name' => 'web',
        ]));
    }

    public function test_store_persists_initial_grid_position(): void
    {
        $this->actingAs($this->user)
            ->post(route('dine-tables.store'), [
                'name' => 'Meja Sudut',
                'capacity' => 4,
                'shape' => 'square',
                'pos_x' => 7,
                'pos_y' => 9,
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('dine_tables', [
            'name' => 'Meja Sudut',
            'pos_x' => 7,
            'pos_y' => 9,
        ]);
    }

    public function test_store_rejects_positions_outside_floor_plan_grid(): void
    {
        $this->actingAs($this->user)
            ->post(route('dine-tables.store'), [
                'name' => 'Meja Invalid',
                'capacity' => 4,
                'shape' => 'square',
                'pos_x' => 25,
                'pos_y' => 15,
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->assertSessionHasErrors(['pos_x', 'pos_y']);

        $this->assertDatabaseMissing('dine_tables', ['name' => 'Meja Invalid']);
    }

    public function test_index_filters_active_and_inactive_tables_independently(): void
    {
        $area = DineArea::create(['name' => 'Area Test', 'is_active' => true]);
        $active = DiningTable::create(['dine_area_id' => $area->id, 'name' => 'Aktif', 'is_active' => true]);
        DiningTable::create(['dine_area_id' => $area->id, 'name' => 'Nonaktif', 'is_active' => false]);

        $this->actingAs($this->user)
            ->get(route('dine-tables.index', ['is_active' => 'active']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/DineIn/Tables/Index')
                ->has('tables', 1)
                ->where('tables.0.id', $active->id)
                ->where('filters.is_active', 'active'));

        $this->actingAs($this->user)
            ->get(route('dine-tables.index', ['is_active' => 'inactive']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/DineIn/Tables/Index')
                ->has('tables', 1)
                ->where('tables.0.name', 'Nonaktif')
                ->where('filters.is_active', 'inactive'));
    }
}
