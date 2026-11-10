<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\CashierShiftController as AppsCashierShiftController;
use App\Http\Requests\CloseCashierShiftRequest;
use App\Http\Requests\StoreCashierShiftRequest;
use App\Http\Traits\ApiResponder;
use App\Models\CashierShift;
use App\Models\Warehouse;
use App\Services\ThermalPrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CashierShiftController extends AppsCashierShiftController
{
    use ApiResponder;

    /**
     * GET /api/v1/cashier-shifts
     * Paginated shift list with filters.
     */
    public function listShifts(Request $request): JsonResponse
    {
        $filters = [
            'cashier_id' => $request->input('cashier_id'),
            'status' => $request->input('status'),
            'opened_from' => $request->input('opened_from'),
            'opened_to' => $request->input('opened_to'),
        ];

        $query = CashierShift::query()
            ->with(['user:id,name', 'openedBy:id,name', 'closedBy:id,name', 'warehouse:id,code,name'])
            ->when($filters['cashier_id'], fn ($builder, $cashierId) => $builder->where('user_id', $cashierId))
            ->when($filters['status'], fn ($builder, $status) => $builder->where('status', $status))
            ->when($filters['opened_from'], fn ($builder, $date) => $builder->whereDate('opened_at', '>=', $date))
            ->when($filters['opened_to'], fn ($builder, $date) => $builder->whereDate('opened_at', '<=', $date))
            ->latest('opened_at');

        $query = $this->cashierShiftService->visibleToUser($query, $request->user());

        $shifts = $query->paginate($this->perPage())->withQueryString();
        $shifts->through(fn (CashierShift $shift) => $this->transformShift($shift));

        return $this->paginated($shifts);
    }

    /**
     * POST /api/v1/cashier-shifts
     * Open a cashier shift.
     */
    public function open(StoreCashierShiftRequest $request): JsonResponse
    {
        $warehouse = $request->validated('warehouse_id')
            ? Warehouse::find($request->validated('warehouse_id'))
            : $this->outletAccessService->salesWarehousesFor($request->user())->first();

        if (! $this->outletAccessService->canSellAtWarehouse($request->user(), $warehouse)) {
            return $this->forbidden('Outlet tidak dapat digunakan oleh pengguna ini.');
        }

        $shift = $this->cashierShiftService->openShift(
            cashier: $request->user(),
            actor: $request->user(),
            openingCash: (int) $request->validated('opening_cash'),
            notes: $request->validated('notes'),
            warehouseId: $warehouse?->id,
        );

        $this->auditLogService->log(
            event: 'cashier_shift.opened',
            module: 'cashier_shifts',
            auditable: $shift,
            description: 'Shift kasir dibuka.',
            after: $this->shiftAuditPayload($shift),
            meta: [
                'cashier_id' => $shift->user_id,
                'opened_by' => $shift->opened_by,
            ],
        );

        return $this->created($this->transformShift($shift->load('warehouse')), 'Shift kasir berhasil dibuka.');
    }

    /**
     * GET /api/v1/cashier-shifts/{cashierShift}
     * Shift detail with summary.
     */
    public function showShift(Request $request, CashierShift $cashierShift): JsonResponse
    {
        $cashierShift = $this->resolveVisibleShift($request, $cashierShift);

        return $this->ok($this->transformShift($cashierShift));
    }

    /**
     * POST /api/v1/cashier-shifts/{cashierShift}/close
     * Close a shift. Force-closing another cashier's shift requires step-up.
     */
    public function closeShift(CloseCashierShiftRequest $request, CashierShift $cashierShift): JsonResponse
    {
        $cashierShift = $this->resolveVisibleShift($request, $cashierShift);
        $forceClose = $cashierShift->user_id !== $request->user()->id;

        if ($forceClose && ! ($request->user()->isSuperAdmin() || $request->user()->can('cashier-shifts-force-close'))) {
            return $this->forbidden();
        }

        $before = $this->shiftAuditPayload($cashierShift);

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
            after: $this->shiftAuditPayload($closedShift),
            meta: [
                'cashier_id' => $closedShift->user_id,
                'closed_by' => $closedShift->closed_by,
            ],
        );

        return $this->ok($this->transformShift($closedShift), 'Shift kasir berhasil ditutup.');
    }

    /**
     * POST /api/v1/cashier-shifts/{cashierShift}/cash-movements
     * Record a cash in/out movement.
     */
    public function addCashMovement(Request $request, CashierShift $cashierShift): JsonResponse
    {
        $cashierShift = $this->resolveVisibleShift($request, $cashierShift);

        if ($cashierShift->user_id !== $request->user()->id
            && ! ($request->user()->isSuperAdmin() || $request->user()->can('cashier-shifts-force-close'))) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'type' => ['required', 'in:in,out'],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->cashierShiftService->recordCashMovement(
            shift: $cashierShift,
            actor: $request->user(),
            type: $validated['type'],
            amount: (int) $validated['amount'],
            note: $validated['note'] ?? null,
        );

        return $this->created(null, 'Pergerakan kas berhasil dicatat.');
    }

    /**
     * GET /api/v1/cashier-shifts/{cashierShift}/report/{type}
     * Thermal-printable shift report HTML (X or Z).
     */
    public function printReport(Request $request, CashierShift $cashierShift, string $type = 'X'): Response
    {
        abort_unless(in_array(strtoupper($type), ['X', 'Z'], true), 404);

        $cashierShift = $this->resolveVisibleShift($request, $cashierShift);

        $html = app(ThermalPrintService::class)->generateShiftReportHtml($cashierShift, strtoupper($type));

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }
}
