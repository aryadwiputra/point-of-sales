<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\RoleController as RootRoleController;
use App\Http\Traits\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends RootRoleController
{
    use ApiResponder;

    /**
     * GET /api/v1/roles
     */
    public function listRoles(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->with('permissions:id,name')
            ->when($request->input('search'), fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->select('id', 'name')
            ->latest()
            ->paginate($this->perPage())
            ->withQueryString();

        return $this->paginated($roles);
    }

    /**
     * POST /api/v1/roles
     */
    public function storeRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
            'selectedPermission' => ['required', 'array'],
            'selectedPermission.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->givePermissionTo($validated['selectedPermission']);

        $this->auditLogService->log(
            event: 'role.created',
            module: 'roles',
            auditable: $role,
            description: 'Role baru dibuat.',
            after: [
                'name' => $role->name,
                'permissions' => $this->auditLogService->permissionNames($validated['selectedPermission']),
            ],
        );

        return $this->created($role->load('permissions:id,name'));
    }

    /**
     * PUT /api/v1/roles/{role}
     */
    public function updateRole(Request $request, Role $role): JsonResponse
    {
        abort_if($role->name === 'super-admin', 403, 'Role super-admin tidak dapat diubah.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name,'.$role->id],
            'selectedPermission' => ['required', 'array'],
            'selectedPermission.*' => ['string', 'exists:permissions,name'],
        ]);

        $beforePermissions = $role->permissions()->pluck('name')->all();
        $before = ['name' => $role->name, 'permissions' => array_values($beforePermissions)];

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['selectedPermission']);

        $afterPermissions = $this->auditLogService->permissionNames($validated['selectedPermission']);

        $this->auditLogService->log(
            event: 'role.updated',
            module: 'roles',
            auditable: $role,
            description: 'Role diperbarui.',
            before: $before,
            after: ['name' => $role->fresh()->name, 'permissions' => array_values($afterPermissions)],
        );

        if ($beforePermissions !== $afterPermissions) {
            $this->auditLogService->log(
                event: 'role.permission_changed',
                module: 'roles',
                auditable: $role,
                description: 'Permission role diperbarui.',
                before: ['permissions' => array_values($beforePermissions)],
                after: ['permissions' => array_values($afterPermissions)],
            );
        }

        return $this->ok($role->fresh()->load('permissions:id,name'));
    }

    /**
     * DELETE /api/v1/roles/{role}
     */
    public function destroyRole(Role $role): JsonResponse
    {
        abort_if($role->name === 'super-admin', 403, 'Role super-admin tidak dapat dihapus.');

        $before = ['name' => $role->name, 'permissions' => $role->permissions()->pluck('name')->all()];

        $role->delete();

        $this->auditLogService->log(
            event: 'role.deleted',
            module: 'roles',
            auditable: $role,
            description: 'Role dihapus.',
            before: $before,
        );

        return $this->noContent();
    }
}
