<?php

namespace Tests\Feature\Inventory;

use App\Models\Outlet;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OutletAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutletAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_outlet_installation_keeps_unassigned_user_compatible(): void
    {
        $outlet = Outlet::create(['code' => 'PUSAT', 'name' => 'Pusat']);
        $warehouse = Warehouse::create([
            'code' => 'PUSAT', 'name' => 'Gudang Pusat', 'type' => 'main',
            'is_active' => true, 'sort_order' => 0, 'outlet_id' => $outlet->id,
        ]);

        $this->assertTrue(app(OutletAccessService::class)->canUseWarehouse(User::factory()->create(), $warehouse));
    }

    public function test_user_can_only_use_assigned_outlet_warehouses(): void
    {
        $allowed = Outlet::create(['code' => 'MAL', 'name' => 'Malabar']);
        $blocked = Outlet::create(['code' => 'PUT', 'name' => 'Puter']);
        $allowedWarehouse = Warehouse::create([
            'code' => 'MAL', 'name' => 'Malabar', 'type' => 'branch',
            'is_active' => true, 'sort_order' => 0, 'outlet_id' => $allowed->id,
        ]);
        $blockedWarehouse = Warehouse::create([
            'code' => 'PUT', 'name' => 'Puter', 'type' => 'branch',
            'is_active' => true, 'sort_order' => 1, 'outlet_id' => $blocked->id,
        ]);
        $user = User::factory()->create();
        $user->outlets()->attach($allowed->id, ['is_default' => true]);

        $service = app(OutletAccessService::class);
        $this->assertTrue($service->canUseWarehouse($user, $allowedWarehouse));
        $this->assertFalse($service->canUseWarehouse($user, $blockedWarehouse));
        $this->assertSame([$allowedWarehouse->id], $service->warehousesFor($user)->pluck('id')->all());
    }
}
