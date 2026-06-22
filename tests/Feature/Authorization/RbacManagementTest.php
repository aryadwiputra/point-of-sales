<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_and_update_role(): void
    {
        $admin = $this->createSuperAdmin();
        $firstPermission = Permission::create([
            'name' => 'users-access',
            'guard_name' => 'web',
        ]);
        $secondPermission = Permission::create([
            'name' => 'roles-access',
            'guard_name' => 'web',
        ]);

        $this->actingAs($admin)
            ->withSession($this->stepUpSession())
            ->post(route('roles.store'), [
                'name' => 'operator',
                'selectedPermission' => [$firstPermission->id],
            ])
            ->assertRedirect();

        $role = Role::query()->where('name', 'operator')->firstOrFail();

        $this->assertTrue($role->hasPermissionTo('users-access'));
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'role.created',
            'auditable_id' => $role->id,
        ]);

        $this->actingAs($admin)
            ->withSession($this->stepUpSession())
            ->put(route('roles.update', $role), [
                'name' => 'supervisor',
                'selectedPermission' => [$secondPermission->id],
            ])
            ->assertRedirect();

        $role->refresh();

        $this->assertSame('supervisor', $role->name);
        $this->assertFalse($role->hasPermissionTo('users-access'));
        $this->assertTrue($role->hasPermissionTo('roles-access'));
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'role.permission_changed',
            'auditable_id' => $role->id,
        ]);
    }

    public function test_super_admin_can_create_update_and_delete_user(): void
    {
        $admin = $this->createSuperAdmin();
        Role::create([
            'name' => 'cashier',
            'guard_name' => 'web',
        ]);
        Role::create([
            'name' => 'manager',
            'guard_name' => 'web',
        ]);

        $this->actingAs($admin)
            ->withSession($this->stepUpSession())
            ->post(route('users.store'), [
                'name' => 'Kasir Baru',
                'email' => 'cashier@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'selectedRoles' => ['cashier'],
            ])
            ->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'cashier@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('cashier'));
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.created',
            'auditable_id' => $user->id,
        ]);

        $this->actingAs($admin)
            ->withSession($this->stepUpSession())
            ->put(route('users.update', $user), [
                'name' => 'Kasir Supervisor',
                'email' => 'cashier@example.com',
                'password' => '',
                'selectedRoles' => ['manager'],
            ])
            ->assertRedirect(route('users.index'));

        $user->refresh();

        $this->assertSame('Kasir Supervisor', $user->name);
        $this->assertTrue($user->hasRole('manager'));
        $this->assertFalse($user->hasRole('cashier'));
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.role_changed',
            'auditable_id' => $user->id,
        ]);

        $this->actingAs($admin)
            ->withSession($this->stepUpSession())
            ->delete(route('users.destroy', $user))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
        $this->assertSame(1, AuditLog::query()->where('event', 'user.deleted')->count());
    }

    public function test_permission_index_filters_by_search_term(): void
    {
        $admin = $this->createSuperAdmin();
        Permission::create([
            'name' => 'users-access',
            'guard_name' => 'web',
        ]);
        Permission::create([
            'name' => 'roles-access',
            'guard_name' => 'web',
        ]);

        $this->actingAs($admin)
            ->get(route('permissions.index', ['search' => 'users']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Permissions/Index')
                ->has('permissions.data', 1)
                ->where('permissions.data.0.name', 'users-access'));
    }

    private function createSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]));

        return $user;
    }

    private function stepUpSession(): array
    {
        return [
            'auth.password_confirmed_at' => now()->timestamp,
        ];
    }
}
