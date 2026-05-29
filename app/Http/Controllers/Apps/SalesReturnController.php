<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesReturn\CompleteSalesReturnRequest;
use App\Http\Requests\SalesReturn\CreateSalesReturnRequest;
use App\Http\Requests\SalesReturn\IndexSalesReturnRequest;
use App\Http\Requests\SalesReturn\ShowSalesReturnRequest;
use App\Http\Requests\SalesReturn\StoreSalesReturnRequest;
use App\Http\Requests\SalesReturn\UpdateSalesReturnRequest;
use App\Models\SalesReturn;
use App\Models\Transaction;
use App\Services\SalesReturns\CompleteSalesReturnService;
use App\Services\SalesReturns\CreateSalesReturnService;
use App\Services\SalesReturns\SalesReturnCreateQueryService;
use App\Services\SalesReturns\SalesReturnIndexQueryService;
use App\Services\SalesReturns\SalesReturnShowQueryService;
use App\Services\SalesReturns\UpdateSalesReturnService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SalesReturnController extends Controller
{
    public function index(IndexSalesReturnRequest $request, SalesReturnIndexQueryService $service): Response
    {
        return Inertia::render(
            'Dashboard/SalesReturns/Index',
            $service->execute($request->filters(), $request->user())
        );
    }

    public function create(
        Transaction $transaction,
        CreateSalesReturnRequest $request,
        SalesReturnCreateQueryService $service
    ): Response|RedirectResponse {
        $payload = $service->execute($transaction, $request->user());

        if ($payload === null) {
            return to_route('transactions.history')->with('error', 'Seluruh item transaksi ini sudah habis diretur.');
        }

        return Inertia::render('Dashboard/SalesReturns/Create', $payload);
    }

    public function store(
        StoreSalesReturnRequest $request,
        Transaction $transaction,
        CreateSalesReturnService $service
    ): RedirectResponse {
        $salesReturn = $service->execute($transaction, $request->validated(), $request->user());

        return to_route('sales-returns.show', $salesReturn)->with('success', 'Draft retur penjualan berhasil dibuat.');
    }

    public function show(
        SalesReturn $salesReturn,
        ShowSalesReturnRequest $request,
        SalesReturnShowQueryService $service
    ): Response {
        return Inertia::render(
            'Dashboard/SalesReturns/Show',
            $service->execute($salesReturn, $request->user())
        );
    }

    public function update(
        UpdateSalesReturnRequest $request,
        SalesReturn $salesReturn,
        UpdateSalesReturnService $service
    ): RedirectResponse {
        $service->execute($salesReturn, $request->validated(), $request->user());

        return back()->with('success', 'Draft retur penjualan berhasil diperbarui.');
    }

    public function complete(
        CompleteSalesReturnRequest $request,
        SalesReturn $salesReturn,
        CompleteSalesReturnService $service
    ): RedirectResponse {
        $service->execute($salesReturn, $request->user());

        return back()->with('success', 'Retur penjualan berhasil diselesaikan.');
    }
}
