<?php

declare(strict_types=1);

namespace App\Services\AuditLogs;

use App\Models\AuditLog;

class AuditLogPayloadService
{
    public function summary(AuditLog $log): array
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

    public function detail(AuditLog $log): array
    {
        return [
            ...$this->summary($log),
            'auditable_type' => $log->auditable_type,
            'auditable_id' => $log->auditable_id,
            'before' => $log->before,
            'after' => $log->after,
            'meta' => $log->meta,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
        ];
    }
}
