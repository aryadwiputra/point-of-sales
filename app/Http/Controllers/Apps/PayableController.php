<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Payable;
use App\Models\PayablePayment;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PayableController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'status' => $request->input('status'),
            'supplier' => $request->input('supplier'),
            'invoice' => $request->input('invoice'),
            'due_from' => $request->input('due_from'),
            'due_to' => $request->input('due_to'),
        ];

        $query = Payable::with('supplier:id,name')
            ->withSum('payments as total_paid', 'amount')
            ->orderByDesc('created_at');

        $query->when($filters['status'], function ($q, $status) {
            $q->where('status', $status);
        })->when($filters['supplier'], function ($q, $supplier) {
            $q->where('supplier_id', $supplier);
        })->when($filters['invoice'], function ($q, $invoice) {
            $q->where('document_number', 'like', '%'.$invoice.'%');
        })->when($filters['due_from'], function ($q, $date) {
            $q->whereDate('due_date', '>=', $date);
        })->when($filters['due_to'], function ($q, $date) {
            $q->whereDate('due_date', '<=', $date);
        });

        $payables = $query->paginate($this->perPage())->withQueryString();
        $payables->getCollection()->transform(function ($item) {
            if ($item->status !== 'paid' && $item->due_date && now()->gt($item->due_date)) {
                $item->status = 'overdue';
            }

            return $item;
        });

        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Dashboard/Payables/Index', [
            'payables' => $payables,
            'filters' => $filters,
            'suppliers' => $suppliers,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'total' => ['required', 'numeric', 'min:1'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        if (! $data['document_number']) {
            $data['document_number'] = 'INV-'.Str::upper(Str::random(8));
        }
        $data['status'] = 'unpaid';
        $data['paid'] = 0;

        Payable::create($data);

        return redirect()
            ->route('payables.index')
            ->with('success', 'Hutang supplier berhasil dibuat.');
    }

    public function show(Payable $payable)
    {
        $payable->load([
            'supplier:id,name,phone,email,address',
            'purchaseOrder:id,document_number,status',
            'payments' => function ($query) {
                $query->orderByDesc('paid_at')->with(['bankAccount:id,bank_name,account_number,account_name,logo', 'user:id,name']);
            },
        ]);
        $bankAccounts = BankAccount::active()->ordered()->get(['id', 'bank_name', 'account_number', 'account_name', 'logo']);

        return Inertia::render('Dashboard/Payables/Show', [
            'payable' => $payable,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function supplierStatement(Request $request)
    {
        $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
        ]);

        $supplier = Supplier::findOrFail($request->input('supplier_id'));

        $payables = Payable::where('supplier_id', $supplier->id)
            ->withSum('payments as total_paid', 'amount')
            ->orderBy('due_date')
            ->get();

        $payables->transform(function ($item) {
            if ($item->status !== 'paid' && $item->due_date && now()->gt($item->due_date)) {
                $item->status = 'overdue';
            }
            $daysOverdue = $item->status === 'overdue' && $item->due_date
                ? now()->diffInDays($item->due_date)
                : 0;

            $item->aging_bucket = match (true) {
                $item->status === 'paid' => 'paid',
                $daysOverdue <= 0 => 'current',
                $daysOverdue <= 30 => '0-30',
                $daysOverdue <= 60 => '31-60',
                $daysOverdue <= 90 => '61-90',
                default => '90+',
            };

            return $item;
        });

        $agingSummary = $payables->groupBy('aging_bucket')->map(function ($group, $bucket) {
            return [
                'bucket' => $bucket,
                'count' => $group->count(),
                'total' => $group->sum('total'),
                'paid' => $group->sum('total_paid'),
                'remaining' => $group->sum(fn ($p) => max(0, $p->total - $p->total_paid)),
            ];
        })->values();

        return response()->json([
            'supplier' => $supplier,
            'payables' => $payables,
            'aging_summary' => $agingSummary,
            'total_outstanding' => $payables->where('status', '!=', 'paid')->sum(fn ($p) => max(0, $p->total - $p->total_paid)),
        ]);
    }

    public function pay(Request $request, Payable $payable)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', 'string', 'max:30'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($validated, $payable, $request) {
            // ponytail: lock the payable row so concurrent payments cannot double-apply past the remaining balance
            $payable = Payable::whereKey($payable->id)->lockForUpdate()->firstOrFail();

            $remaining = $payable->remaining;
            if ($validated['amount'] > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal melebihi sisa hutang.',
                ]);
            }

            PayablePayment::create([
                'payable_id' => $payable->id,
                'paid_at' => $validated['paid_at'],
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'note' => $validated['note'] ?? null,
                'user_id' => $request->user()->id,
            ]);

            $payable->paid = ($payable->paid ?? 0) + $validated['amount'];
            $remaining = max(0, ($payable->total ?? 0) - ($payable->paid ?? 0));
            $payable->status = $remaining <= 0 ? 'paid' : 'partial';
            if ($payable->status !== 'paid' && $payable->due_date && now()->gt($payable->due_date)) {
                $payable->status = 'overdue';
            }
            $payable->save();
        });

        return redirect()
            ->route('payables.show', $payable)
            ->with('success', 'Pembayaran hutang berhasil dicatat.');
    }
}
