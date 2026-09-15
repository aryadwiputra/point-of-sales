<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Services\OutletAccessService;
use App\Services\ReceivableService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ReceivableController extends Controller
{
    public function __construct(
        private readonly ReceivableService $receivableService,
        private readonly OutletAccessService $outletAccessService,
    ) {}

    public function index(Request $request)
    {
        $filters = [
            'status' => $request->input('status'),
            'customer' => $request->input('customer'),
            'invoice' => $request->input('invoice'),
            'due_from' => $request->input('due_from'),
            'due_to' => $request->input('due_to'),
        ];

        $warehouseIds = $this->outletAccessService->warehousesFor($request->user())->pluck('id');
        $query = $this->receivableService->scopeQuery(Receivable::with('customer:id,name'), $warehouseIds)
            ->withSum('payments as total_paid', 'amount')
            ->orderByDesc('created_at');

        $query->when($filters['status'], function ($q, $status) {
            $q->where('status', $status);
        })->when($filters['customer'], function ($q, $customer) {
            $q->where('customer_id', $customer);
        })->when($filters['invoice'], function ($q, $invoice) {
            $q->where('invoice', 'like', '%'.$invoice.'%');
        })->when($filters['due_from'], function ($q, $date) {
            $q->whereDate('due_date', '>=', $date);
        })->when($filters['due_to'], function ($q, $date) {
            $q->whereDate('due_date', '<=', $date);
        });

        $receivables = $query->paginate($this->perPage())->withQueryString();
        $receivables->getCollection()->transform(function ($item) {
            if ($item->status !== 'paid' && $item->due_date && now()->gt($item->due_date)) {
                $item->status = 'overdue';
            }

            return $item;
        });

        return Inertia::render('Dashboard/Receivables/Index', [
            'receivables' => $receivables,
            'filters' => $filters,
        ]);
    }

    public function show(Receivable $receivable)
    {
        $this->ensureAccess($receivable);
        $receivable->load([
            'customer:id,name,no_telp',
            'transaction',
            'payments' => function ($query) {
                $query->orderByDesc('paid_at')->with(['bankAccount:id,bank_name,account_number,account_name,logo', 'user:id,name']);
            },
        ]);

        $outlet = $this->outletAccessService->activeOutlet(request());
        $bankAccounts = BankAccount::active()->forOutlet($outlet)->ordered()->get(['id', 'bank_name', 'account_number', 'account_name', 'logo']);

        return Inertia::render('Dashboard/Receivables/Show', [
            'receivable' => $receivable,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function pay(Request $request, Receivable $receivable)
    {
        $this->ensureAccess($receivable);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', 'string', 'max:30'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($validated, $receivable, $request) {
            // ponytail: lock the receivable row so concurrent payments cannot double-apply past the remaining balance
            $receivable = Receivable::whereKey($receivable->id)->lockForUpdate()->firstOrFail();

            $remaining = $receivable->remaining;
            if ($validated['amount'] > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal melebihi sisa piutang.',
                ]);
            }

            if (! empty($validated['bank_account_id'])) {
                abort_unless(
                    BankAccount::active()->forOutlet($this->outletAccessService->activeOutlet($request))
                        ->whereKey($validated['bank_account_id'])->exists(),
                    422,
                    'Rekening bank tidak tersedia untuk outlet aktif.'
                );
            }

            ReceivablePayment::create([
                'receivable_id' => $receivable->id,
                'paid_at' => $validated['paid_at'],
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'note' => $validated['note'] ?? null,
                'user_id' => $request->user()->id,
            ]);

            $receivable->paid = ($receivable->paid ?? 0) + $validated['amount'];
            $remaining = max(0, ($receivable->total ?? 0) - ($receivable->paid ?? 0));
            $receivable->status = $remaining <= 0 ? 'paid' : 'partial';
            if ($receivable->status !== 'paid' && $receivable->due_date && now()->gt($receivable->due_date)) {
                $receivable->status = 'overdue';
            }
            $receivable->save();

            if ($receivable->transaction) {
                $receivable->transaction->update([
                    'payment_status' => $receivable->status === 'paid' ? 'paid' : 'unpaid',
                ]);
            }
        });

        return redirect()
            ->route('receivables.show', $receivable)
            ->with('success', 'Pembayaran piutang berhasil dicatat.');
    }

    public function aging()
    {
        $warehouseIds = $this->outletAccessService->warehousesFor(request()->user())->pluck('id');
        $summary = $this->receivableService->getAgingSummary($warehouseIds);
        $topCustomers = $this->receivableService->getTopCustomersByReceivable(10, $warehouseIds);
        $collectionRate = $this->receivableService->getCollectionRate($warehouseIds);

        return response()->json([
            'aging_summary' => $summary,
            'top_customers' => $topCustomers,
            'collection_rate' => $collectionRate,
        ]);
    }

    public function customerStatement(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
        ]);

        $warehouseIds = $this->outletAccessService->warehousesFor($request->user())->pluck('id');
        $data = $this->receivableService->getCustomerStatement($validated['customer_id'], $warehouseIds);

        return response()->json($data);
    }

    public function updateCollectionNotes(Request $request, Receivable $receivable)
    {
        $this->ensureAccess($receivable);
        $validated = $request->validate([
            'collection_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $receivable->update(['collection_notes' => $validated['collection_notes'] ?? null]);

        return back()->with('success', 'Catatan penagihan berhasil disimpan.');
    }

    private function ensureAccess(Receivable $receivable): void
    {
        $warehouse = $receivable->transaction?->warehouse;
        abort_unless($this->outletAccessService->canUseWarehouse(request()->user(), $warehouse), 404);
    }
}
