<?php

declare(strict_types=1);

namespace App\Services\Roles;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleIndexQueryService
{
    public function execute(?string $search = null): array
    {
        return [
            'roles' => Role::query()
                ->with('permissions')
                ->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                ->select('id', 'name')
                ->latest()
                ->paginate(7)
                ->withQueryString(),
            'permissions' => Permission::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ];
    }
}
