<?php

declare(strict_types=1);

namespace App\Services\Roles;

use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class DeleteRoleService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function execute(Role $role): void
    {
        DB::transaction(function () use ($role) {
            $before = [
                'name' => $role->name,
                'permissions' => $role->permissions()->pluck('name')->sort()->values()->all(),
            ];

            $role->delete();

            $this->auditLogService->log(
                event: 'role.deleted',
                module: 'roles',
                auditable: $role,
                description: 'Role dihapus.',
                before: $before,
            );
        });
    }
}
