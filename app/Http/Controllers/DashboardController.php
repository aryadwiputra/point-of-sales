<?php
namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $totalCategories   = Category::count();
        $totalProducts     = Product::count();
        $totalTransactions = Transaction::count();
        $totalUsers        = User::count();

        return Inertia::render('Dashboard/Index', [
            'totalCategories'   => $totalCategories,
            'totalProducts'     => $totalProducts,
            'totalTransactions' => $totalTransactions,
            'totalUsers'        => $totalUsers,
        ]);
    }
}
