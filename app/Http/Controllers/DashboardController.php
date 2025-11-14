<?php
namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Profit;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $totalCategories   = Category::count();
        $totalProducts     = Product::count();
        $totalTransactions = Transaction::count();
        $totalUsers        = User::count();
        $totalRevenue      = Transaction::sum('grand_total');
        $totalProfit       = Profit::sum('total');
        $averageOrder      = Transaction::avg('grand_total') ?? 0;
        $todayTransactions = Transaction::whereDate('created_at', Carbon::today())->count();

        $revenueTrend      = Transaction::selectRaw('DATE(created_at) as date, SUM(grand_total) as total')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->take(12)
            ->get()
            ->map(function ($row) {
                return [
                    'date'  => $row->date,
                    'label' => Carbon::parse($row->date)->format('d M'),
                    'total' => (int) $row->total,
                ];
            })
            ->reverse()
            ->values();

        $topProducts = TransactionDetail::select('product_id', DB::raw('SUM(qty) as qty'), DB::raw('SUM(price) as total'))
            ->with('product:id,title')
            ->groupBy('product_id')
            ->orderByDesc('qty')
            ->take(5)
            ->get()
            ->map(function ($detail) {
                return [
                    'name'  => $detail->product?->title ?? 'Produk terhapus',
                    'qty'   => (int) $detail->qty,
                    'total' => (int) $detail->total,
                ];
            });

        $recentTransactions = Transaction::with('cashier:id,name', 'customer:id,name')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($transaction) {
                return [
                    'invoice'  => $transaction->invoice,
                    'date'     => Carbon::parse($transaction->created_at)->format('d M Y'),
                    'customer' => $transaction->customer?->name ?? '-',
                    'cashier'  => $transaction->cashier?->name ?? '-',
                    'total'    => (int) $transaction->grand_total,
                ];
            });

        $topCustomers = Transaction::select('customer_id', DB::raw('COUNT(*) as orders'), DB::raw('SUM(grand_total) as total'))
            ->with('customer:id,name')
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->orderByDesc('total')
            ->take(5)
            ->get()
            ->map(function ($row) {
                return [
                    'name'   => $row->customer?->name ?? 'Pelanggan',
                    'orders' => (int) $row->orders,
                    'total'  => (int) $row->total,
                ];
            });

        return Inertia::render('Dashboard/Index', [
            'totalCategories'   => $totalCategories,
            'totalProducts'     => $totalProducts,
            'totalTransactions' => $totalTransactions,
            'totalUsers'        => $totalUsers,
            'revenueTrend'      => $revenueTrend,
            'totalRevenue'      => (int) $totalRevenue,
            'totalProfit'       => (int) $totalProfit,
            'averageOrder'      => (int) round($averageOrder),
            'todayTransactions' => (int) $todayTransactions,
            'topProducts'       => $topProducts,
            'recentTransactions'=> $recentTransactions,
            'topCustomers'      => $topCustomers,
        ]);
    }
}
