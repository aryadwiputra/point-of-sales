<?php

declare(strict_types=1);

namespace App\Services\CashierShifts;

use App\Models\CashierShift;
use App\Models\User;

class CashierShiftShowQueryService
{
    public function __construct(
        private readonly CashierShiftPayloadService $payloadService,
        private readonly CashierShiftVisibilityService $visibilityService
    ) {}

    public function execute(CashierShift $cashierShift, User $user): array
    {
        $cashierShift = $this->visibilityService->resolveVisibleShift($cashierShift, $user);

        return [
            'cashierShift' => $this->payloadService->displayPayload($cashierShift),
            'canForceClose' => $this->visibilityService->canForceClose($user),
        ];
    }
}
