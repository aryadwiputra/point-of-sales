<?php

declare(strict_types=1);

namespace App\Services\Roles;

use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class CreateRoleService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function execute(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::create(['name' => $data['name']]);
            $role->syncPermissions($data['selectedPermission']);

            $this->auditLogService->log(
                event: 'role.created',
                module: 'roles',
                auditable: $role,
                description: 'Role baru dibuat.',
                after: [
                    'name' => $role->name,
                    'permissions' => $role->permissions()
                        ->pluck('name')
                        ->sort()
                        ->values()
                        ->all(),
                ],
            );

            return $role;
        });
    }
}
