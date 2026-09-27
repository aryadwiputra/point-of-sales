<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrivilegeGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'dashboard-access',
            'users-access',
            'users-create',
            'users-update',
            'users-delete',
            'roles-access',
            'roles-create',
            'roles-update',
            'roles-delete',
        ] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    }

    private function adminWith(array $permissions): User
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    public function test_user_cannot_delete_own_account(): void
    {
        $admin = $this->adminWith(['users-access', 'users-delete']);

        $response = $this->actingAs($admin)
            ->withSession($this->recentlyConfirmedSession())
            ->delete(route('users.destroy', $admin->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_last_super_admin_cannot_be_deleted(): void
    {
        $admin = $this->adminWith(['users-access', 'users-delete']);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($admin)
            ->withSession($this->recentlyConfirmedSession())
            ->delete(route('users.destroy', $superAdmin->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
    }

    public function test_super_admin_can_be_deleted_when_another_remains(): void
    {
        $admin = $this->adminWith(['users-access', 'users-delete']);
        $target = User::factory()->create();
        $target->assignRole('super-admin');
        $other = User::factory()->create();
        $other->assignRole('super-admin');

        $this->actingAs($admin)
            ->withSession($this->recentlyConfirmedSession())
            ->delete(route('users.destroy', $target->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_super_admin_role_cannot_be_updated(): void
    {
        $admin = $this->adminWith(['roles-access', 'roles-update']);
        $role = Role::where('name', 'super-admin')->firstOrFail();

        $this->actingAs($admin)
            ->withSession($this->recentlyConfirmedSession())
            ->put(route('roles.update', $role->id), [
                'name' => 'super-admin',
                'selectedPermission' => [],
            ])
            ->assertForbidden();
    }

    public function test_super_admin_role_cannot_be_deleted(): void
    {
        $admin = $this->adminWith(['roles-access', 'roles-delete']);
        $role = Role::where('name', 'super-admin')->firstOrFail();

        $this->actingAs($admin)
            ->withSession($this->recentlyConfirmedSession())
            ->delete(route('roles.destroy', $role->id))
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['name' => 'super-admin']);
    }
}
