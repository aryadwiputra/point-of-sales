<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashierShift\CloseCashierShiftRequest;
use App\Http\Requests\CashierShift\ConfirmPasswordForForceCloseRequest;
use App\Http\Requests\CashierShift\IndexCashierShiftRequest;
use App\Http\Requests\CashierShift\ShowCashierShiftRequest;
use App\Http\Requests\CashierShift\StoreCashierShiftRequest;
use App\Models\CashierShift;
use App\Services\CashierShifts\CashierShiftIndexQueryService;
use App\Services\CashierShifts\CashierShiftShowQueryService;
use App\Services\CashierShifts\CloseCashierShiftService;
use App\Services\CashierShifts\OpenCashierShiftService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CashierShiftController extends Controller
{
    public function index(IndexCashierShiftRequest $request, CashierShiftIndexQueryService $service): Response
    {
        return Inertia::render(
            'Dashboard/CashierShifts/Index',
            $service->execute($request->filters(), $request->user())
        );
    }

    public function show(
        ShowCashierShiftRequest $request,
        CashierShift $cashierShift,
        CashierShiftShowQueryService $service
    ): Response {
        return Inertia::render(
            'Dashboard/CashierShifts/Show',
            $service->execute($cashierShift, $request->user())
        );
    }

    public function store(StoreCashierShiftRequest $request, OpenCashierShiftService $service): RedirectResponse
    {
        $shift = $service->execute($request->user(), $request->validated());

        $target = $request->input('redirect_to') === 'transactions'
            ? route('transactions.index')
            : route('cashier-shifts.show', $shift);

        return redirect($target)->with('success', 'Shift kasir berhasil dibuka.');
    }

    public function close(
        CloseCashierShiftRequest $request,
        CashierShift $cashierShift,
        ConfirmPasswordForForceCloseRequest $confirmPasswordRequest,
        CloseCashierShiftService $service
    ): RedirectResponse {
        $result = $service->execute($request, $cashierShift, $confirmPasswordRequest);

        if ($result['requires_password_confirmation']) {
            return redirect()->route('password.confirm');
        }

        return to_route('cashier-shifts.show', $result['shift'])->with('success', 'Shift kasir berhasil ditutup.');
    }
}
