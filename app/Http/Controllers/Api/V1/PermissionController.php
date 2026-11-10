<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\PermissionController as RootPermissionController;
use App\Http\Traits\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends RootPermissionController
{
    use ApiResponder;

    /**
     * GET /api/v1/permissions
     */
    public function listPermissions(Request $request): JsonResponse
    {
        $permissions = Permission::query()
            ->when($request->input('search'), fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->select('id', 'name')
            ->latest()
            ->paginate($this->perPage())
            ->withQueryString();

        return $this->paginated($permissions);
    }
}
