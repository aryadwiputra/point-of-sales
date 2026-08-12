<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'user_id' => $request->input('user_id'),
            'module' => $request->input('module'),
            'event' => $request->input('event'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'search' => $request->input('search'),
        ];

        $auditLogs = AuditLog::query()
            ->with('user:id,name,email')
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

        return Inertia::render('Dashboard/AuditLogs/Index', [
            'auditLogs' => $auditLogs,
            'filters' => $filters,
            'users' => User::query()->select('id', 'name')->orderBy('name')->get(),
            'modules' => AuditLog::query()->select('module')->distinct()->orderBy('module')->pluck('module'),
            'events' => AuditLog::query()->select('event')->distinct()->orderBy('event')->pluck('event'),
        ]);
    }

    public function show(AuditLog $auditLog): Response
    {
        $auditLog->load('user:id,name,email');

        return Inertia::render('Dashboard/AuditLogs/Show', [
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

    private function transformSummary(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'event' => $log->event,
            'module' => $log->module,
            'target_label' => $log->target_label,
            'description' => $log->description,
            'created_at' => optional($log->created_at)?->toISOString(),
            'user' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
                'email' => $log->user->email,
            ] : null,
        ];
    }
}
