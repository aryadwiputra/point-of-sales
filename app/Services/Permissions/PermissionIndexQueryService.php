<?php

declare(strict_types=1);

namespace App\Services\Permissions;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Permission;

class PermissionIndexQueryService
{
    public function execute(?string $search = null): LengthAwarePaginator
    {
        return Permission::query()
            ->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->select('id', 'name')
            ->latest()
            ->paginate(7)
            ->withQueryString();
    }
}
