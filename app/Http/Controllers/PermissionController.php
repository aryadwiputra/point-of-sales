<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Permission\IndexPermissionRequest;
use App\Services\Permissions\PermissionIndexQueryService;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function index(IndexPermissionRequest $request, PermissionIndexQueryService $service): Response
    {
        return Inertia::render('Dashboard/Permissions/Index', [
            'permissions' => $service->execute($request->search()),
        ]);
    }
}
