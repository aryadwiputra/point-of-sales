<?php

namespace Tests\Feature\Fnb;

use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModifierAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        $this->admin = User::where('email', 'arya@gmail.com')->firstOrFail();
        $this->admin->markEmailAsVerified();

        $this->cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
        $this->cashier->markEmailAsVerified();
    }

    public function test_admin_can_manage_groups_and_options(): void
    {
        $this->actingAs($this->admin)->post(route('modifiers.groups.store'), [
            'name' => 'Ukuran',
            'selection_type' => 'single',
            'min_select' => 1,
            'max_select' => 1,
            'sort_order' => 0,
            'is_active' => true,
        ])->assertRedirect();

        $group = ModifierGroup::where('name', 'Ukuran')->firstOrFail();

        $this->actingAs($this->admin)->post(route('modifiers.options.store'), [
            'modifier_group_id' => $group->id,
            'name' => 'Large',
            'price_delta' => 5000,
            'sort_order' => 0,
            'is_active' => true,
        ])->assertRedirect();

        $option = Modifier::where('name', 'Large')->firstOrFail();

        $this->actingAs($this->admin)->patch(route('modifiers.options.update', $option->id), [
            'modifier_group_id' => $group->id,
            'name' => 'Extra Large',
            'price_delta' => 8000,
            'sort_order' => 1,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertSame('Extra Large', $option->fresh()->name);
        $this->assertSame(8000, (int) $option->fresh()->price_delta);

        $this->actingAs($this->admin)->delete(route('modifiers.groups.destroy', $group->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('modifier_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('modifiers', ['id' => $option->id]);
    }

    public function test_cashier_cannot_manage_modifiers(): void
    {
        $this->actingAs($this->cashier)->get(route('modifiers.index'))->assertForbidden();

        $this->actingAs($this->cashier)->post(route('modifiers.groups.store'), [
            'name' => 'Ukuran',
            'selection_type' => 'single',
            'min_select' => 1,
            'max_select' => 1,
        ])->assertForbidden();
    }
}
