<?php

namespace Tests\Feature\Kitchen;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_kitchen_role_has_kds_permissions_only(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $cook = User::factory()->create();
        $cook->assignRole('kitchen');

        $this->assertTrue($cook->can('kitchen-access'));
        $this->assertTrue($cook->can('kitchen-process'));
        $this->assertFalse($cook->can('kitchen-manage'));
        $this->assertFalse($cook->can('dashboard-access'));
        $this->assertFalse($cook->can('transactions-access'));
    }

    public function test_manager_gets_manage_and_cashier_gets_nothing(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $this->assertTrue($manager->can('kitchen-manage'));

        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $this->assertFalse($cashier->can('kitchen-access'));
    }
}
