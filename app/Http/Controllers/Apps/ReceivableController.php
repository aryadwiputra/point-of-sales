<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\Receivable\GetCustomerStatementRequest;
use App\Http\Requests\Receivable\IndexReceivableRequest;
use App\Http\Requests\Receivable\PayReceivableRequest;
use App\Http\Requests\Receivable\UpdateReceivableCollectionNotesRequest;
use App\Models\Receivable;
use App\Services\Receivables\CreateReceivablePaymentService;
use App\Services\Receivables\ReceivableIndexQueryService;
use App\Services\Receivables\ReceivableShowQueryService;
use App\Services\Receivables\UpdateReceivableCollectionNotesService;
use App\Services\ReceivableService;
use Inertia\Inertia;

class ReceivableController extends Controller
{
    public function __construct(
        private readonly ReceivableService $receivableService
    ) {}

    public function index(IndexReceivableRequest $request, ReceivableIndexQueryService $service)
    {
        return Inertia::render('Dashboard/Receivables/Index', $service->execute($request->filters()));
    }

    public function show(Receivable $receivable, ReceivableShowQueryService $service)
    {
        return Inertia::render('Dashboard/Receivables/Show', $service->execute($receivable));
    }

    public function pay(
        PayReceivableRequest $request,
        Receivable $receivable,
        CreateReceivablePaymentService $service
    ) {
        if (! $service->execute($receivable, $request->validated(), $request->user()->id)) {
            return back()->with('error', 'Nominal melebihi sisa piutang.');
        }

        return redirect()
            ->route('receivables.show', $receivable)
            ->with('success', 'Pembayaran piutang berhasil dicatat.');
    }

    public function aging()
    {
        $summary = $this->receivableService->getAgingSummary();
        $topCustomers = $this->receivableService->getTopCustomersByReceivable(10);
        $collectionRate = $this->receivableService->getCollectionRate();

        return response()->json([
            'aging_summary' => $summary,
            'top_customers' => $topCustomers,
            'collection_rate' => $collectionRate,
        ]);
    }

    public function customerStatement(GetCustomerStatementRequest $request)
    {
        $data = $this->receivableService->getCustomerStatement((int) $request->validated('customer_id'));

        return response()->json($data);
    }

    public function updateCollectionNotes(
        UpdateReceivableCollectionNotesRequest $request,
        Receivable $receivable,
        UpdateReceivableCollectionNotesService $service
    ) {
        $service->execute($receivable, $request->validated('collection_notes'));

        return back()->with('success', 'Catatan penagihan berhasil disimpan.');
    }
}
