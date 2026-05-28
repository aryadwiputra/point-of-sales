<?php

declare(strict_types=1);

namespace App\Services\CashierShifts;

use App\Models\CashierShift;
use App\Models\User;
use App\Services\CashierShiftService;

class CashierShiftVisibilityService
{
    public function __construct(
        private readonly CashierShiftService $cashierShiftService
    ) {}

    public function resolveVisibleShift(CashierShift $cashierShift, User $user): CashierShift
    {
        $query = CashierShift::query()
            ->with(['user:id,name', 'openedBy:id,name', 'closedBy:id,name'])
            ->whereKey($cashierShift->id);

        $query = $this->cashierShiftService->visibleToUser($query, $user);

        return $query->firstOrFail();
    }

    public function canForceClose(User $user): bool
    {
        return $user->isSuperAdmin() || $user->can('cashier-shifts-force-close');
    }
}
