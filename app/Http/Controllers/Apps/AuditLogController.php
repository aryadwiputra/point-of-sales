<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLog\IndexAuditLogRequest;
use App\Models\AuditLog;
use App\Services\AuditLogs\AuditLogDetailQueryService;
use App\Services\AuditLogs\AuditLogIndexQueryService;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(IndexAuditLogRequest $request, AuditLogIndexQueryService $service): Response
    {
        return Inertia::render('Dashboard/AuditLogs/Index', $service->execute($request->filters()));
    }

    public function show(AuditLog $auditLog, AuditLogDetailQueryService $service): Response
    {
        return Inertia::render('Dashboard/AuditLogs/Show', [
            'auditLog' => $service->execute($auditLog),
        ]);
    }
}
