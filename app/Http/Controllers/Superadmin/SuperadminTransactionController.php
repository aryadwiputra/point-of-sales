<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SuperadminTransactionController extends Controller
{
    public function index(Request $request): Response
    {
        return TenantContext::withoutScope(function () use ($request) {
            $filters = [
                'search' => trim((string) $request->input('search', '')),
                'tenant_id' => $request->input('tenant_id'),
                'payment_status' => $request->input('payment_status', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ];

            $query = Transaction::query()->with([
                'tenant:id,name,subdomain',
                'cashier:id,name',
                'warehouse:id,name',
            ]);

            $this->applyFilters($query, $filters);

            $transactions = (clone $query)
                ->latest()
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Transaction $transaction) => [
                    'id' => $transaction->id,
                    'invoice' => $transaction->invoice,
                    'grand_total' => (int) $transaction->grand_total,
                    'payment_method' => $transaction->payment_method,
                    'payment_status' => $transaction->payment_status,
                    'created_at' => $transaction->created_at?->toIso8601String(),
                    'tenant' => $transaction->tenant,
                    'cashier' => $transaction->cashier,
                    'warehouse' => $transaction->warehouse,
                ]);

            $summaryQuery = Transaction::query();
            $this->applyFilters($summaryQuery, $filters);

            $summaryRow = (clone $summaryQuery)
                ->selectRaw('COUNT(*) as transactions_count, COALESCE(SUM(grand_total), 0) as revenue_total')
                ->first();

            $transactionsCount = (int) ($summaryRow->transactions_count ?? 0);
            $revenueTotal = (int) round($summaryRow->revenue_total ?? 0);

            $summary = [
                'transactions_count' => $transactionsCount,
                'revenue_total' => $revenueTotal,
                'average_transaction' => $transactionsCount > 0
                    ? (int) round($revenueTotal / $transactionsCount)
                    : 0,
                'tenants_transacting' => (int) (clone $summaryQuery)->distinct()->count('tenant_id'),
            ];

            $monthExpression = DB::connection()->getDriverName() === 'sqlite'
                ? "strftime('%Y-%m', created_at)"
                : "DATE_FORMAT(created_at, '%Y-%m')";

            $monthlyRows = Transaction::query()
                ->where('created_at', '>=', now()->startOfMonth()->subMonths(5))
                ->selectRaw("{$monthExpression} as month_bucket, COUNT(*) as transactions_count, COALESCE(SUM(grand_total), 0) as revenue_total")
                ->groupBy(DB::raw($monthExpression))
                ->get()
                ->keyBy('month_bucket');

            $monthly = collect(range(5, 0))
                ->map(function (int $offset) use ($monthlyRows) {
                    $month = now()->startOfMonth()->subMonths($offset);
                    $key = $month->format('Y-m');
                    $row = $monthlyRows->get($key);

                    return [
                        'month' => $key,
                        'label' => $month->translatedFormat('M Y'),
                        'transactions_count' => (int) ($row->transactions_count ?? 0),
                        'revenue_total' => (int) round($row->revenue_total ?? 0),
                    ];
                })
                ->values()
                ->all();

            $topTenants = Transaction::query()
                ->whereNotNull('tenant_id')
                ->selectRaw('tenant_id, COUNT(*) as transactions_count, COALESCE(SUM(grand_total), 0) as revenue_total')
                ->groupBy('tenant_id')
                ->orderByDesc('revenue_total')
                ->limit(5)
                ->with('tenant:id,name,subdomain')
                ->get()
                ->map(fn (Transaction $row) => [
                    'tenant_id' => $row->tenant_id,
                    'name' => $row->tenant?->name ?? 'Tenant #'.$row->tenant_id,
                    'subdomain' => $row->tenant?->subdomain,
                    'transactions_count' => (int) $row->transactions_count,
                    'revenue_total' => (int) round($row->revenue_total),
                ])
                ->all();

            return Inertia::render('Superadmin/Transactions/Index', [
                'transactions' => $transactions,
                'summary' => $summary,
                'monthly' => $monthly,
                'topTenants' => $topTenants,
                'tenants' => Tenant::orderBy('name')->get(['id', 'name', 'subdomain']),
                'filters' => $filters,
            ]);
        });
    }

    protected function applyFilters($query, array $filters): void
    {
        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice', 'like', "%{$search}%")
                    ->orWhere('payment_reference', 'like', "%{$search}%");
            });
        }

        if ($filters['tenant_id']) {
            $query->where('tenant_id', (int) $filters['tenant_id']);
        }

        if ($filters['payment_status'] !== '') {
            $query->where('payment_status', $filters['payment_status']);
        }

        if ($filters['date_from'] !== '') {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if ($filters['date_to'] !== '') {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }
    }
}
