<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\AuditLogController as AppsAuditLogController;
use App\Http\Traits\ApiResponder;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends AppsAuditLogController
{
    use ApiResponder;

    /**
     * GET /api/v1/audit-logs
     */
    public function listAuditLogs(Request $request): JsonResponse
    {
        $filters = [
            'user_id' => $request->input('user_id'),
            'module' => $request->input('module'),
            'event' => $request->input('event'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
        ];

        $visibleUserIds = $this->visibleUserIds($request->user());

        $auditLogs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($visibleUserIds !== null, fn (Builder $query) => $query->whereIn('user_id', $visibleUserIds))
            ->when($filters['user_id'], fn (Builder $query, $userId) => $query->where('user_id', $userId))
            ->when($filters['module'], fn (Builder $query, $module) => $query->where('module', $module))
            ->when($filters['event'], fn (Builder $query, $event) => $query->where('event', $event))
            ->when($filters['date_from'], fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'], fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['search'], function (Builder $query, $search) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder
                        ->where('target_label', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->latest('created_at')
            ->paginate($this->perPage())
            ->withQueryString();

        $auditLogs->through(fn (AuditLog $log) => $this->transformSummary($log));

        return $this->ok([
            'auditLogs' => $auditLogs,
            'filters' => $filters,
            'users' => User::query()
                ->when($visibleUserIds !== null, fn (Builder $query) => $query->whereIn('id', $visibleUserIds))
                ->select('id', 'name')->orderBy('name')->get(),
            'modules' => AuditLog::query()
                ->when($visibleUserIds !== null, fn (Builder $query) => $query->whereIn('user_id', $visibleUserIds))
                ->select('module')->distinct()->orderBy('module')->pluck('module'),
            'events' => AuditLog::query()
                ->when($visibleUserIds !== null, fn (Builder $query) => $query->whereIn('user_id', $visibleUserIds))
                ->select('event')->distinct()->orderBy('event')->pluck('event'),
        ]);
    }

    /**
     * GET /api/v1/audit-logs/{auditLog}
     */
    public function showAuditLog(AuditLog $auditLog): JsonResponse
    {
        $visibleUserIds = $this->visibleUserIds(request()->user());
        abort_if($visibleUserIds !== null && ! in_array($auditLog->user_id, $visibleUserIds, true), 404);

        $auditLog->load('user:id,name,email');

        return $this->ok([
            'auditLog' => [
                ...$this->transformSummary($auditLog),
                'auditable_type' => $auditLog->auditable_type,
                'auditable_id' => $auditLog->auditable_id,
                'before' => $auditLog->before,
                'after' => $auditLog->after,
                'meta' => $auditLog->meta,
                'ip_address' => $auditLog->ip_address,
                'user_agent' => $auditLog->user_agent,
            ],
        ]);
    }
}
