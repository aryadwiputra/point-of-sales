<?php

declare(strict_types=1);

namespace App\Services\Roles;

use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UpdateRoleService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function execute(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $beforePermissions = $role->permissions()
                ->pluck('name')
                ->sort()
                ->values()
                ->all();
            $before = [
                'name' => $role->name,
                'permissions' => $beforePermissions,
            ];

            $role->update(['name' => $data['name']]);
            $role->syncPermissions($data['selectedPermission']);

            $afterPermissions = $this->auditLogService->permissionNames(
                $role->permissions()->pluck('name')->sort()->values()
            );

            $this->auditLogService->log(
                event: 'role.updated',
                module: 'roles',
                auditable: $role,
                description: 'Role diperbarui.',
                before: $before,
                after: [
                    'name' => $role->fresh()->name,
                    'permissions' => $afterPermissions,
                ],
            );

            if ($beforePermissions !== $afterPermissions) {
                $this->auditLogService->log(
                    event: 'role.permission_changed',
                    module: 'roles',
                    auditable: $role,
                    description: 'Permission role diperbarui.',
                    before: ['permissions' => $beforePermissions],
                    after: ['permissions' => $afterPermissions],
                );
            }

            return $role->fresh();
        });
    }
}
