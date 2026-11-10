<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\ReceivableController as AppsReceivableController;
use App\Http\Traits\ApiResponder;
use App\Models\BankAccount;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivableController extends AppsReceivableController
{
    use ApiResponder;

    /**
     * GET /api/v1/receivables
     * Paginated, filterable receivables list.
     */
    public function listReceivables(Request $request): JsonResponse
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

        return $this->paginated($receivables);
    }

    /**
     * GET /api/v1/receivables/aging
     * Aging summary, top customers, collection rate.
     */
    public function agingSummary(Request $request): JsonResponse
    {
        $warehouseIds = $this->outletAccessService->warehousesFor($request->user())->pluck('id');

        return $this->ok([
            'aging_summary' => $this->receivableService->getAgingSummary($warehouseIds),
            'top_customers' => $this->receivableService->getTopCustomersByReceivable(10, $warehouseIds),
            'collection_rate' => $this->receivableService->getCollectionRate($warehouseIds),
        ]);
    }

    /**
     * GET /api/v1/receivables/customer-statement
     * Full receivable statement for one customer.
     */
    public function customerStatement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
        ]);

        $warehouseIds = $this->outletAccessService->warehousesFor($request->user())->pluck('id');
        $data = $this->receivableService->getCustomerStatement($validated['customer_id'], $warehouseIds);

        return $this->ok($data);
    }

    /**
     * GET /api/v1/receivables/{receivable}
     */
    public function showReceivable(Request $request, Receivable $receivable): JsonResponse
    {
        $this->ensureAccess($receivable);
        $receivable->load([
            'customer:id,name,no_telp',
            'transaction',
            'payments' => function ($query) {
                $query->orderByDesc('paid_at')->with(['bankAccount:id,bank_name,account_number,account_name,logo', 'user:id,name']);
            },
        ]);

        $outlet = $this->outletAccessService->activeOutlet($request);
        $bankAccounts = BankAccount::active()->forOutlet($outlet)->ordered()->get(['id', 'bank_name', 'account_number', 'account_name', 'logo']);

        return $this->ok([
            'receivable' => $receivable,
            'bank_accounts' => $bankAccounts,
        ]);
    }

    /**
     * PATCH /api/v1/receivables/{receivable}/collection-notes
     */
    public function updateCollectionNotes(Request $request, Receivable $receivable): JsonResponse
    {
        $this->ensureAccess($receivable);
        $validated = $request->validate([
            'collection_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $receivable->update(['collection_notes' => $validated['collection_notes'] ?? null]);

        return $this->ok(['receivable' => $receivable->fresh()]);
    }

    /**
     * POST /api/v1/receivables/{receivable}/pay
     */
    public function payReceivable(Request $request, Receivable $receivable): JsonResponse
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

        return $this->ok(['receivable' => $receivable->fresh()]);
    }
}
