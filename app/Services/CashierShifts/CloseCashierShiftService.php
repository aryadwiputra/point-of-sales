<?php

declare(strict_types=1);

namespace App\Services\CashierShifts;

use App\Http\Requests\CashierShift\CloseCashierShiftRequest;
use App\Http\Requests\CashierShift\ConfirmPasswordForForceCloseRequest;
use App\Models\CashierShift;
use App\Services\AuditLogService;
use App\Services\CashierShiftService;

class CloseCashierShiftService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CashierShiftService $cashierShiftService,
        private readonly CashierShiftPayloadService $payloadService,
        private readonly CashierShiftVisibilityService $visibilityService
    ) {}

    public function execute(
        CloseCashierShiftRequest $request,
        CashierShift $cashierShift,
        ConfirmPasswordForForceCloseRequest $confirmPasswordRequest
    ): array {
        $cashierShift = $this->visibilityService->resolveVisibleShift($cashierShift, $request->user());
        $before = $this->payloadService->auditPayload($cashierShift);
        $forceClose = $cashierShift->user_id !== $request->user()->id;

        if ($forceClose && ! $this->visibilityService->canForceClose($request->user())) {
            abort(403);
        }

        if ($forceClose && ! $confirmPasswordRequest->recentlyConfirmed()) {
            $intended = $request->headers->get('referer') ?: route('cashier-shifts.show', $cashierShift);

            $request->session()->put('url.intended', $intended);
            $request->session()->put('security.step_up_context', [
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'intended' => $intended,
                'target' => $cashierShift->id,
            ]);

            $this->auditLogService->log(
                event: 'security.privileged_action_challenged',
                module: 'security',
                auditable: $cashierShift,
                description: 'Force close shift memerlukan konfirmasi password ulang.',
                meta: [
                    'severity' => 'high',
                    'route' => $request->route()?->getName(),
                ],
            );

            return [
                'requires_password_confirmation' => true,
                'shift' => $cashierShift,
            ];
        }

        $closedShift = $this->cashierShiftService->closeShift(
            shift: $cashierShift,
            actor: $request->user(),
            actualCash: (int) $request->validated('actual_cash'),
            closeNotes: $request->validated('close_notes'),
            forceClose: $forceClose,
        );

        $this->auditLogService->log(
            event: $forceClose ? 'cashier_shift.force_closed' : 'cashier_shift.closed',
            module: 'cashier_shifts',
            auditable: $closedShift,
            description: $forceClose ? 'Shift kasir ditutup paksa.' : 'Shift kasir ditutup.',
            before: $before,
            after: $this->payloadService->auditPayload($closedShift),
            meta: [
                'cashier_id' => $closedShift->user_id,
                'closed_by' => $closedShift->closed_by,
            ],
        );

        return [
            'requires_password_confirmation' => false,
            'shift' => $closedShift,
        ];
    }
}
