<?php

declare(strict_types=1);

namespace App\Services\CashierShifts;

use App\Models\CashierShift;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CashierShiftService;

class OpenCashierShiftService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CashierShiftService $cashierShiftService,
        private readonly CashierShiftPayloadService $payloadService
    ) {}

    public function execute(User $user, array $data): CashierShift
    {
        $shift = $this->cashierShiftService->openShift(
            cashier: $user,
            actor: $user,
            openingCash: (int) $data['opening_cash'],
            notes: $data['notes'] ?? null,
        );

        $this->auditLogService->log(
            event: 'cashier_shift.opened',
            module: 'cashier_shifts',
            auditable: $shift,
            description: 'Shift kasir dibuka.',
            after: $this->payloadService->auditPayload($shift),
            meta: [
                'cashier_id' => $shift->user_id,
                'opened_by' => $shift->opened_by,
            ],
        );

        return $shift;
    }
}
