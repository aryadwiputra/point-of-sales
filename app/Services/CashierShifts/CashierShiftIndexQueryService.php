<?php

declare(strict_types=1);

namespace App\Services\CashierShifts;

use App\Models\CashierShift;
use App\Models\User;
use App\Services\CashierShiftService;
use Illuminate\Database\Eloquent\Builder;

class CashierShiftIndexQueryService
{
    public function __construct(
        private readonly CashierShiftService $cashierShiftService,
        private readonly CashierShiftPayloadService $payloadService,
        private readonly CashierShiftVisibilityService $visibilityService
    ) {}

    public function execute(array $filters, User $user): array
    {
        $query = CashierShift::query()
            ->with(['user:id,name', 'openedBy:id,name', 'closedBy:id,name'])
            ->when($filters['cashier_id'], fn (Builder $builder, $cashierId) => $builder->where('user_id', $cashierId))
            ->when($filters['status'], fn (Builder $builder, $status) => $builder->where('status', $status))
            ->when($filters['opened_from'], fn (Builder $builder, $date) => $builder->whereDate('opened_at', '>=', $date))
            ->when($filters['opened_to'], fn (Builder $builder, $date) => $builder->whereDate('opened_at', '<=', $date))
            ->latest('opened_at');

        $query = $this->cashierShiftService->visibleToUser($query, $user);

        $shifts = $query->paginate(10)->withQueryString();
        $shifts->through(fn (CashierShift $shift) => $this->payloadService->displayPayload($shift));

        $activeShift = $this->cashierShiftService->getActiveShiftForUser($user->id);
        $canForceClose = $this->visibilityService->canForceClose($user);

        return [
            'shifts' => $shifts,
            'filters' => $filters,
            'cashiers' => $canForceClose
                ? User::query()->orderBy('name')->get(['id', 'name'])
                : collect([$user->only(['id', 'name'])]),
            'activeShift' => $activeShift ? $this->payloadService->displayPayload($activeShift) : null,
            'canForceClose' => $canForceClose,
        ];
    }
}
