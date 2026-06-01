<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Role\IndexRoleRequest;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Services\Roles\CreateRoleService;
use App\Services\Roles\DeleteRoleService;
use App\Services\Roles\RoleIndexQueryService;
use App\Services\Roles\UpdateRoleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(IndexRoleRequest $request, RoleIndexQueryService $service): Response
    {
        return Inertia::render('Dashboard/Roles/Index', $service->execute($request->search()));
    }

    public function store(StoreRoleRequest $request, CreateRoleService $service): RedirectResponse
    {
        $service->execute($request->validated());

        return back();
    }

    public function update(
        UpdateRoleRequest $request,
        Role $role,
        UpdateRoleService $service
    ): RedirectResponse {
        $service->execute($role, $request->validated());

        return back();
    }

    public function destroy(Role $role, DeleteRoleService $service): RedirectResponse
    {
        $service->execute($role);

        return back();
    }
}
