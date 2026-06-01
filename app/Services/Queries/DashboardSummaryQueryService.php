<?php

declare(strict_types=1);

namespace App\Services\Queries;

use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Profit;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\CashierShiftService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardSummaryQueryService
{
    public function __construct(
        private readonly CashierShiftService $cashierShiftService
    ) {}

    public function execute(): array
    {
        $today = Carbon::today();
        $now = Carbon::now();
        $averageOrder = Transaction::avg('grand_total') ?? 0;

        return [
            'totalCategories' => Category::count(),
            'totalProducts' => Product::count(),
            'totalTransactions' => Transaction::count(),
            'totalCustomers' => Customer::count(),
            'revenueTrend' => $this->revenueTrend(),
            'totalRevenue' => (int) Transaction::sum('grand_total'),
            'totalProfit' => (int) Profit::sum('total'),
            'averageOrder' => (int) round((float) $averageOrder),
            'todayTransactions' => Transaction::whereDate('created_at', $today)->count(),
            'todaySales' => (int) Transaction::whereDate('created_at', $today)->sum('grand_total'),
            'todayProfit' => (int) Profit::whereDate('created_at', $today)->sum('total'),
            'monthlyTarget' => Setting::getInt('monthly_sales_target'),
            'currentMonthSales' => (int) Transaction::whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->sum('grand_total'),
            'topProducts' => $this->topProducts(),
            'lowStockProducts' => $this->lowStockProducts(),
            'slowMovingProducts' => $this->slowMovingProducts(),
            'recentTransactions' => $this->recentTransactions(),
            'topCustomers' => $this->topCustomers(),
            'topLocations' => $this->topLocations(),
            'activeShifts' => $this->activeShifts(),
        ];
    }

    private function revenueTrend(): Collection
    {
        return Transaction::selectRaw('DATE(created_at) as date, SUM(grand_total) as total')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->take(12)
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'label' => Carbon::parse($row->date)->format('d M'),
                'total' => (int) $row->total,
            ])
            ->reverse()
            ->values();
    }

    private function topProducts(): Collection
    {
        return TransactionDetail::select('product_id', DB::raw('SUM(qty) as qty'), DB::raw('SUM(price) as total'))
            ->with('product:id,title,sku')
            ->groupBy('product_id')
            ->orderByDesc('qty')
            ->take(3)
            ->get()
            ->map(fn (TransactionDetail $detail) => [
                'name' => $detail->product?->title ?? 'Produk terhapus',
                'sku' => $detail->product?->sku ?? '-',
                'qty' => (int) $detail->qty,
                'total' => (int) $detail->total,
            ]);
    }

    private function lowStockProducts(): Collection
    {
        return Product::where('stock', '<', 10)
            ->orderBy('stock')
            ->take(5)
            ->get()
            ->map(fn (Product $product) => [
                'name' => $product->title,
                'stock' => (int) $product->stock,
                'image' => $product->image,
            ]);
    }

    private function slowMovingProducts(): Collection
    {
        $recentlySoldProductIds = TransactionDetail::where('created_at', '>=', Carbon::now()->subDays(30))
            ->distinct()
            ->pluck('product_id');

        return Product::whereNotIn('id', $recentlySoldProductIds)
            ->where('stock', '>', 0)
            ->take(5)
            ->get()
            ->map(fn (Product $product) => [
                'name' => $product->title,
                'stock' => (int) $product->stock,
                'image' => $product->image,
            ]);
    }

    private function recentTransactions(): Collection
    {
        return Transaction::with('cashier:id,name', 'customer:id,name')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'invoice' => $transaction->invoice,
                'date' => Carbon::parse($transaction->created_at)->format('d M Y'),
                'customer' => $transaction->customer?->name ?? '-',
                'cashier' => $transaction->cashier?->name ?? '-',
                'total' => (int) $transaction->grand_total,
            ]);
    }

    private function topCustomers(): Collection
    {
        return Transaction::select('customer_id', DB::raw('COUNT(*) as orders'), DB::raw('SUM(grand_total) as total'))
            ->with('customer:id,name')
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->orderByDesc('total')
            ->take(5)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'name' => $transaction->customer?->name ?? 'Pelanggan',
                'orders' => (int) $transaction->orders,
                'total' => (int) $transaction->total,
            ]);
    }

    private function topLocations(): Collection
    {
        return Transaction::join('customers', 'transactions.customer_id', '=', 'customers.id')
            ->select('customers.village_name', DB::raw('COUNT(*) as orders'))
            ->whereNotNull('customers.village_name')
            ->groupBy('customers.village_name')
            ->orderByDesc('orders')
            ->take(5)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->village_name ?? 'Lainnya',
                'orders' => (int) $row->orders,
            ]);
    }

    private function activeShifts(): Collection
    {
        return CashierShift::query()
            ->with('user:id,name')
            ->open()
            ->latest('opened_at')
            ->take(5)
            ->get()
            ->map(function (CashierShift $shift) {
                $summary = $this->cashierShiftService->calculateSummary($shift);

                return [
                    'id' => $shift->id,
                    'opened_at' => optional($shift->opened_at)?->toISOString(),
                    'opening_cash' => (int) $shift->opening_cash,
                    'expected_cash' => $summary['expected_cash'],
                    'transactions_count' => $summary['transactions_count'],
                    'cash_sales_total' => $summary['cash_sales_total'],
                    'user' => [
                        'id' => $shift->user?->id,
                        'name' => $shift->user?->name,
                    ],
                ];
            })
            ->values();
    }
}
