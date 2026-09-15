<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseCashierShiftRequest;
use App\Http\Requests\ConfirmPasswordForForceCloseRequest;
use App\Http\Requests\StoreCashierShiftRequest;
use App\Models\CashierShift;
use App\Models\ShiftCashMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AuditLogService;
use App\Services\CashierShiftService;
use App\Services\OutletAccessService;
use App\Services\ThermalPrintService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashierShiftController extends Controller
{
    public function __construct(
        private readonly CashierShiftService $cashierShiftService,
        private readonly AuditLogService $auditLogService,
        private readonly OutletAccessService $outletAccessService
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'cashier_id' => $request->input('cashier_id'),
            'status' => $request->input('status'),
            'opened_from' => $request->input('opened_from'),
            'opened_to' => $request->input('opened_to'),
        ];

        $query = CashierShift::query()
            ->with(['user:id,name', 'openedBy:id,name', 'closedBy:id,name', 'warehouse:id,code,name'])
            ->when($filters['cashier_id'], fn (Builder $builder, $cashierId) => $builder->where('user_id', $cashierId))
            ->when($filters['status'], fn (Builder $builder, $status) => $builder->where('status', $status))
            ->when($filters['opened_from'], fn (Builder $builder, $date) => $builder->whereDate('opened_at', '>=', $date))
            ->when($filters['opened_to'], fn (Builder $builder, $date) => $builder->whereDate('opened_at', '<=', $date))
            ->latest('opened_at');

        $query = $this->cashierShiftService->visibleToUser($query, $request->user());

        $shifts = $query->paginate($this->perPage())->withQueryString();
        $shifts->through(fn (CashierShift $shift) => $this->transformShift($shift));

        $activeShift = $this->cashierShiftService->getActiveShiftForUser($request->user()->id);
        $cashiers = $request->user()->isSuperAdmin() || $request->user()->can('cashier-shifts-force-close')
            ? User::query()->orderBy('name')->get(['id', 'name'])
            : collect([$request->user()->only(['id', 'name'])]);

        $warehouses = $this->outletAccessService->salesWarehousesFor($request->user());

        return Inertia::render('Dashboard/CashierShifts/Index', [
            'shifts' => $shifts,
            'filters' => $filters,
            'cashiers' => $cashiers,
            'activeShift' => $activeShift ? $this->transformShift($activeShift) : null,
            'warehouses' => $warehouses,
            'canForceClose' => $request->user()->isSuperAdmin() || $request->user()->can('cashier-shifts-force-close'),
        ]);
    }

    public function show(Request $request, CashierShift $cashierShift): Response
    {
        $cashierShift = $this->resolveVisibleShift($request, $cashierShift);

        return Inertia::render('Dashboard/CashierShifts/Show', [
            'cashierShift' => $this->transformShift($cashierShift),
            'canForceClose' => $request->user()->isSuperAdmin() || $request->user()->can('cashier-shifts-force-close'),
        ]);
    }

    public function store(StoreCashierShiftRequest $request): RedirectResponse
    {
        $warehouse = $request->validated('warehouse_id')
            ? Warehouse::find($request->validated('warehouse_id'))
            : Warehouse::active()->orderBy('code')->first();

        abort_unless($this->outletAccessService->canSellAtWarehouse($request->user(), $warehouse), 403);

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

        $target = $request->input('redirect_to') === 'transactions'
            ? route('transactions.index')
            : route('cashier-shifts.show', $shift);

        return redirect($target)->with('success', 'Shift kasir berhasil dibuka.');
    }

    public function close(CloseCashierShiftRequest $request, CashierShift $cashierShift, ConfirmPasswordForForceCloseRequest $confirmPasswordRequest): RedirectResponse
    {
        $cashierShift = $this->resolveVisibleShift($request, $cashierShift);
        $before = $this->shiftAuditPayload($cashierShift);
        $forceClose = $cashierShift->user_id !== $request->user()->id;

        if ($forceClose && ! ($request->user()->isSuperAdmin() || $request->user()->can('cashier-shifts-force-close'))) {
            abort(403);
        }

        if ($forceClose && ! $confirmPasswordRequest->recentlyConfirmed()) {
            $request->session()->put('url.intended', $request->headers->get('referer') ?: route('cashier-shifts.show', $cashierShift));
            $request->session()->put('security.step_up_context', [
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'intended' => $request->headers->get('referer') ?: route('cashier-shifts.show', $cashierShift),
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

            return redirect()->route('password.confirm');
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
            after: $this->shiftAuditPayload($closedShift),
            meta: [
                'cashier_id' => $closedShift->user_id,
                'closed_by' => $closedShift->closed_by,
            ],
        );

        return to_route('cashier-shifts.show', $closedShift)->with('success', 'Shift kasir berhasil ditutup.');
    }

    public function storeCashMovement(Request $request, CashierShift $cashierShift): RedirectResponse
    {
        $cashierShift = $this->resolveVisibleShift($request, $cashierShift);

        if ($cashierShift->user_id !== $request->user()->id && ! ($request->user()->isSuperAdmin() || $request->user()->can('cashier-shifts-force-close'))) {
            abort(403);
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

        return back()->with('success', 'Pergerakan kas berhasil dicatat.');
    }

    public function printReport(Request $request, CashierShift $cashierShift, string $type = 'X')
    {
        abort_unless(in_array(strtoupper($type), ['X', 'Z'], true), 404);

        $cashierShift = $this->resolveVisibleShift($request, $cashierShift);

        $service = app(ThermalPrintService::class);
        $html = $service->generateShiftReportHtml($cashierShift, strtoupper($type));

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    private function resolveVisibleShift(Request $request, CashierShift $cashierShift): CashierShift
    {
        $query = CashierShift::query()
            ->with(['user:id,name', 'openedBy:id,name', 'closedBy:id,name', 'warehouse:id,code,name'])
            ->whereKey($cashierShift->id);

        $query = $this->cashierShiftService->visibleToUser($query, $request->user());

        return $query->firstOrFail();
    }

    private function transformShift(CashierShift $shift): array
    {
        $summary = $this->cashierShiftService->calculateSummary($shift);

        return [
            'id' => $shift->id,
            'status' => $shift->status,
            'opened_at' => optional($shift->opened_at)?->toISOString(),
            'closed_at' => optional($shift->closed_at)?->toISOString(),
            'opening_cash' => (int) $shift->opening_cash,
            'expected_cash' => $shift->isOpen() ? $summary['expected_cash'] : (int) $shift->expected_cash,
            'actual_cash' => $shift->actual_cash !== null ? (int) $shift->actual_cash : null,
            'cash_difference' => $shift->isOpen()
                ? null
                : ($shift->cash_difference !== null ? (int) $shift->cash_difference : null),
            'cash_sales_total' => $shift->isOpen() ? $summary['cash_sales_total'] : (int) $shift->cash_sales_total,
            'non_cash_sales_total' => $shift->isOpen() ? $summary['non_cash_sales_total'] : (int) $shift->non_cash_sales_total,
            'cash_refund_total' => $shift->isOpen() ? $summary['cash_refund_total'] : (int) $shift->cash_refund_total,
            'non_cash_refund_total' => $shift->isOpen() ? $summary['non_cash_refund_total'] : (int) $shift->non_cash_refund_total,
            'cash_in_total' => $shift->isOpen() ? $summary['cash_in_total'] : ($summary['cash_in_total'] ?: 0),
            'cash_out_total' => $shift->isOpen() ? $summary['cash_out_total'] : ($summary['cash_out_total'] ?: 0),
            'cash_movements' => $shift->cashMovements()
                ->with('user:id,name')
                ->latest()
                ->get()
                ->map(fn (ShiftCashMovement $movement) => [
                    'id' => $movement->id,
                    'type' => $movement->type,
                    'amount' => (int) $movement->amount,
                    'note' => $movement->note,
                    'user' => $movement->user?->name,
                    'created_at' => optional($movement->created_at)?->toISOString(),
                ])
                ->values()
                ->all(),
            'transactions_count' => $shift->isOpen() ? $summary['transactions_count'] : (int) $shift->transactions_count,
            'sales_returns_count' => $shift->isOpen() ? $summary['sales_returns_count'] : (int) $shift->sales_returns_count,
            'notes' => $shift->notes,
            'close_notes' => $shift->close_notes,
            'warehouse' => $shift->warehouse ? [
                'id' => $shift->warehouse->id,
                'code' => $shift->warehouse->code,
                'name' => $shift->warehouse->name,
            ] : null,
            'user' => $shift->user ? [
                'id' => $shift->user->id,
                'name' => $shift->user->name,
            ] : null,
            'opened_by' => $shift->openedBy ? [
                'id' => $shift->openedBy->id,
                'name' => $shift->openedBy->name,
            ] : null,
            'closed_by' => $shift->closedBy ? [
                'id' => $shift->closedBy->id,
                'name' => $shift->closedBy->name,
            ] : null,
        ];
    }

    private function shiftAuditPayload(CashierShift $shift): array
    {
        return [
            'status' => $shift->status,
            'opening_cash' => (int) $shift->opening_cash,
            'expected_cash' => (int) ($shift->expected_cash ?? $shift->opening_cash),
            'actual_cash' => $shift->actual_cash !== null ? (int) $shift->actual_cash : null,
            'cash_difference' => $shift->cash_difference !== null ? (int) $shift->cash_difference : null,
            'transactions_count' => (int) ($shift->transactions_count ?? 0),
            'sales_returns_count' => (int) ($shift->sales_returns_count ?? 0),
        ];
    }
}
