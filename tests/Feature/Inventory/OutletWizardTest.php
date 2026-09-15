<?php

namespace Tests\Feature\Inventory;

use App\Models\Outlet;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OutletWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_sales_outlet_with_warehouse(): void
    {
        $this->seedPermissions();
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));
        $user->markEmailAsVerified();

        $response = $this->withSession($this->recentlyConfirmedSession())->actingAs($user)->post(route('settings.outlets.store'), [
            'name' => 'Malabar',
            'code' => 'mal',
            'warehouse_name' => 'Gudang Malabar',
            'warehouse_code' => 'wh-mal',
            'is_sales_enabled' => true,
        ]);

        $response->assertRedirect(route('settings.outlets.index'));
        $outlet = Outlet::where('code', 'MAL')->firstOrFail();
        $this->assertTrue($outlet->is_active);
        $this->assertTrue($outlet->is_sales_enabled);
        $this->assertDatabaseHas('warehouses', [
            'outlet_id' => $outlet->id,
            'code' => 'WH-MAL',
            'type' => 'branch',
        ]);
    }

    public function test_central_warehouse_cannot_open_sales_shift(): void
    {
        $this->seedPermissions();
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('super-admin'));
        $user->markEmailAsVerified();
        $outlet = Outlet::create(['code' => 'PUSAT', 'name' => 'Pusat', 'is_sales_enabled' => false]);
        $warehouse = Warehouse::create([
            'outlet_id' => $outlet->id,
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->post(route('cashier-shifts.store'), [
            'opening_cash' => 0,
            'warehouse_id' => $warehouse->id,
        ]);

        $response->assertForbidden();
    }

    private function seedPermissions(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }
}
