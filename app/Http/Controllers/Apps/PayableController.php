<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payable\GetSupplierStatementRequest;
use App\Http\Requests\Payable\IndexPayableRequest;
use App\Http\Requests\Payable\PayPayableRequest;
use App\Http\Requests\Payable\StorePayableRequest;
use App\Models\Payable;
use App\Services\Payables\CreatePayablePaymentService;
use App\Services\Payables\CreatePayableService;
use App\Services\Payables\PayableIndexQueryService;
use App\Services\Payables\PayableShowQueryService;
use App\Services\Payables\PayableSupplierStatementQueryService;
use Inertia\Inertia;

class PayableController extends Controller
{
    public function index(IndexPayableRequest $request, PayableIndexQueryService $service)
    {
        return Inertia::render('Dashboard/Payables/Index', $service->execute($request->filters()));
    }

    public function store(StorePayableRequest $request, CreatePayableService $service)
    {
        $service->execute($request->validated());

        return redirect()
            ->route('payables.index')
            ->with('success', 'Hutang supplier berhasil dibuat.');
    }

    public function show(Payable $payable, PayableShowQueryService $service)
    {
        return Inertia::render('Dashboard/Payables/Show', $service->execute($payable));
    }

    public function supplierStatement(
        GetSupplierStatementRequest $request,
        PayableSupplierStatementQueryService $service
    ) {
        return response()->json($service->execute((int) $request->validated('supplier_id')));
    }

    public function pay(
        PayPayableRequest $request,
        Payable $payable,
        CreatePayablePaymentService $service
    ) {
        if (! $service->execute($payable, $request->validated(), $request->user()->id)) {
            return back()->with('error', 'Nominal melebihi sisa hutang.');
        }

        return redirect()
            ->route('payables.show', $payable)
            ->with('success', 'Pembayaran hutang berhasil dicatat.');
    }
}
