<?php

declare(strict_types=1);

namespace App\Services\AuditLogs;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AuditLogIndexQueryService
{
    public function __construct(
        private readonly AuditLogPayloadService $payloadService
    ) {}

    public function execute(array $filters): array
    {
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
            ->paginate(15)
            ->withQueryString();

        $auditLogs->through(fn (AuditLog $log) => $this->payloadService->summary($log));

        return [
            'auditLogs' => $auditLogs,
            'filters' => $filters,
            'users' => User::query()->select('id', 'name')->orderBy('name')->get(),
            'modules' => AuditLog::query()->select('module')->distinct()->orderBy('module')->pluck('module'),
            'events' => AuditLog::query()->select('event')->distinct()->orderBy('event')->pluck('event'),
        ];
    }
}
