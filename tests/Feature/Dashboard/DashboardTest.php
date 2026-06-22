<?php

namespace Tests\Feature\Dashboard;

use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-01 10:00:00');

        Permission::firstOrCreate([
            'name' => 'dashboard-access',
            'guard_name' => 'web',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_authorized_user_can_open_dashboard_with_summary_widgets(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('dashboard-access');
        $customer = Customer::create([
            'name' => 'Dashboard Customer',
            'no_telp' => '62811111111',
            'address' => 'Jl. Dashboard',
            'village_name' => 'Sukamaju',
        ]);
        $category = Category::create([
            'name' => 'Dashboard Category',
            'description' => 'Testing',
            'image' => 'category.png',
        ]);
        $bestSeller = $this->createProduct($category, 'Best Seller', 'BEST-001', 5);
        $slowMover = $this->createProduct($category, 'Slow Mover', 'SLOW-001', 4);
        $shift = CashierShift::create([
            'user_id' => $user->id,
            'opened_by' => $user->id,
            'opened_at' => now(),
            'opening_cash' => 100_000,
            'expected_cash' => 100_000,
            'status' => CashierShift::STATUS_OPEN,
        ]);

        Setting::set('monthly_sales_target', 500_000);

        $transaction = Transaction::create([
            'cashier_id' => $user->id,
            'cashier_shift_id' => $shift->id,
            'customer_id' => $customer->id,
            'invoice' => 'INV-DASHBOARD-001',
            'cash' => 100_000,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 100_000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
        $transaction->details()->create([
            'product_id' => $bestSeller->id,
            'qty' => 2,
            'base_unit_price' => 50_000,
            'unit_price' => 50_000,
            'price' => 100_000,
        ]);
        $transaction->profits()->create([
            'total' => 40_000,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->where('totalCategories', 1)
                ->where('totalProducts', 2)
                ->where('totalTransactions', 1)
                ->where('totalCustomers', 1)
                ->where('totalRevenue', 100_000)
                ->where('totalProfit', 40_000)
                ->where('averageOrder', 100_000)
                ->where('todayTransactions', 1)
                ->where('todaySales', 100_000)
                ->where('todayProfit', 40_000)
                ->where('monthlyTarget', 500_000)
                ->where('currentMonthSales', 100_000)
                ->where('topProducts.0.name', 'Best Seller')
                ->where('topProducts.0.qty', 2)
                ->where('lowStockProducts.0.name', 'Slow Mover')
                ->where('slowMovingProducts.0.name', $slowMover->title)
                ->where('recentTransactions.0.invoice', 'INV-DASHBOARD-001')
                ->where('topCustomers.0.name', 'Dashboard Customer')
                ->where('topLocations.0.name', 'Sukamaju')
                ->where('activeShifts.0.transactions_count', 1)
                ->where('activeShifts.0.cash_sales_total', 100_000)
                ->where('activeShifts.0.expected_cash', 200_000));
    }

    public function test_user_without_permission_cannot_open_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    private function createProduct(Category $category, string $title, string $sku, int $stock): Product
    {
        return Product::create([
            'image' => 'product.png',
            'barcode' => $sku,
            'sku' => $sku,
            'title' => $title,
            'description' => 'Testing',
            'buy_price' => 30_000,
            'sell_price' => 50_000,
            'category_id' => $category->id,
            'stock' => $stock,
        ]);
    }
}
