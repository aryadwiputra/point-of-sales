<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierReturn\CreateSupplierReturnRequest;
use App\Http\Requests\SupplierReturn\IndexSupplierReturnRequest;
use App\Http\Requests\SupplierReturn\StoreSupplierReturnRequest;
use App\Models\SupplierReturn;
use App\Services\SupplierReturns\CancelSupplierReturnService;
use App\Services\SupplierReturns\CompleteSupplierReturnService;
use App\Services\SupplierReturns\CreateSupplierReturnService;
use App\Services\SupplierReturns\SupplierReturnCreateQueryService;
use App\Services\SupplierReturns\SupplierReturnIndexQueryService;
use App\Services\SupplierReturns\SupplierReturnShowQueryService;
use Inertia\Inertia;

class SupplierReturnController extends Controller
{
    public function index(IndexSupplierReturnRequest $request, SupplierReturnIndexQueryService $service)
    {
        return Inertia::render('Dashboard/SupplierReturns/Index', $service->execute($request->filters()));
    }

    public function create(CreateSupplierReturnRequest $request, SupplierReturnCreateQueryService $service)
    {
        return Inertia::render(
            'Dashboard/SupplierReturns/Create',
            $service->execute($request->integer('supplier_id') ?: null)
        );
    }

    public function store(StoreSupplierReturnRequest $request, CreateSupplierReturnService $service)
    {
        $return = $service->execute($request->validated(), $request->user()->id);

        return redirect()
            ->route('supplier-returns.show', $return)
            ->with('success', 'Retur supplier berhasil dibuat.');
    }

    public function show(SupplierReturn $supplierReturn, SupplierReturnShowQueryService $service)
    {
        return Inertia::render('Dashboard/SupplierReturns/Show', $service->execute($supplierReturn));
    }

    public function complete(SupplierReturn $supplierReturn, CompleteSupplierReturnService $service)
    {
        if (! $service->execute($supplierReturn)) {
            return back()->with('error', 'Hanya retur dengan status draft yang bisa diselesaikan.');
        }

        return redirect()
            ->route('supplier-returns.show', $supplierReturn)
            ->with('success', 'Retur supplier berhasil diselesaikan.');
    }

    public function cancel(SupplierReturn $supplierReturn, CancelSupplierReturnService $service)
    {
        if (! $service->execute($supplierReturn)) {
            return back()->with('error', 'Retur tidak dapat dibatalkan.');
        }

        return redirect()
            ->route('supplier-returns.index')
            ->with('success', 'Retur supplier dibatalkan.');
    }
}
