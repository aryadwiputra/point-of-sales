<?php

declare(strict_types=1);

namespace App\Services\AuditLogs;

use App\Models\AuditLog;

class AuditLogDetailQueryService
{
    public function __construct(
        private readonly AuditLogPayloadService $payloadService
    ) {}

    public function execute(AuditLog $auditLog): array
    {
        $auditLog->load('user:id,name,email');

        return $this->payloadService->detail($auditLog);
    }
}
