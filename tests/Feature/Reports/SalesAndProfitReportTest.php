<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SalesAndProfitReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reports-access', 'profits-access'] as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_sales_report_summary_honors_transaction_filters(): void
    {
        $user = $this->createUser();
        $cashier = User::factory()->create();
        $otherCashier = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Report Customer',
            'no_telp' => '62811111111',
            'address' => 'Jl. Report',
        ]);
        $product = $this->createProduct();

        $this->createTransaction(
            $cashier,
            $customer,
            $product,
            'INV-MATCH-001',
            Carbon::parse('2026-05-10 10:00:00'),
            2,
            100_000,
            10_000,
            40_000
        );
        $this->createTransaction(
            $otherCashier,
            $customer,
            $product,
            'INV-OTHER-001',
            Carbon::parse('2026-05-11 10:00:00'),
            1,
            50_000,
            0,
            20_000
        );

        $response = $this->actingAs($user)->get(route('reports.sales.index', [
            'invoice' => 'MATCH',
            'cashier_id' => $cashier->id,
            'start_date' => '2026-05-10',
            'end_date' => '2026-05-10',
        ]));

        $response->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];

            $this->assertSame(1, $props['summary']['orders_count']);
            $this->assertSame(90_000, $props['summary']['revenue_total']);
            $this->assertSame(10_000, $props['summary']['discount_total']);
            $this->assertSame(2, $props['summary']['items_sold']);
            $this->assertSame(40_000, $props['summary']['profit_total']);
            $this->assertSame('INV-MATCH-001', $props['transactions']['data'][0]['invoice']);
        });
    }

    public function test_profit_report_summary_honors_transaction_filters(): void
    {
        $user = $this->createUser();
        $cashier = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Profit Customer',
            'no_telp' => '62812222222',
            'address' => 'Jl. Profit',
        ]);
        $product = $this->createProduct();

        $this->createTransaction(
            $cashier,
            $customer,
            $product,
            'INV-PROFIT-HIGH',
            Carbon::parse('2026-05-12 10:00:00'),
            2,
            120_000,
            0,
            60_000
        );
        $this->createTransaction(
            $cashier,
            $customer,
            $product,
            'INV-PROFIT-LOW',
            Carbon::parse('2026-05-13 10:00:00'),
            1,
            50_000,
            0,
            20_000
        );

        $response = $this->actingAs($user)->get(route('reports.profits.index', [
            'customer_id' => $customer->id,
        ]));

        $response->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];

            $this->assertSame(80_000, $props['summary']['profit_total']);
            $this->assertSame(170_000, $props['summary']['revenue_total']);
            $this->assertSame(2, $props['summary']['orders_count']);
            $this->assertSame(3, $props['summary']['items_sold']);
            $this->assertSame(40_000, $props['summary']['average_profit']);
            $this->assertSame('INV-PROFIT-HIGH', $props['summary']['best_invoice']);
            $this->assertSame(60_000, $props['summary']['best_profit']);
        });
    }

    public function test_report_requests_reject_reversed_date_range(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('reports.sales.index', [
            'start_date' => '2026-05-10',
            'end_date' => '2026-05-09',
        ]));

        $response
            ->assertSessionHasErrors('end_date')
            ->assertRedirect();
    }

    private function createUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['reports-access', 'profits-access']);

        return $user;
    }

    private function createProduct(): Product
    {
        $category = Category::create([
            'name' => 'Report Category',
            'description' => 'Testing',
            'image' => 'category.png',
        ]);

        return Product::create([
            'image' => 'product.png',
            'barcode' => 'REPORT-'.strtoupper((string) str()->random(8)),
            'sku' => 'REPORT-'.strtoupper((string) str()->random(8)),
            'title' => 'Report Product',
            'description' => 'Testing',
            'buy_price' => 30_000,
            'sell_price' => 50_000,
            'category_id' => $category->id,
            'stock' => 10,
        ]);
    }

    private function createTransaction(
        User $cashier,
        Customer $customer,
        Product $product,
        string $invoice,
        Carbon $createdAt,
        int $quantity,
        int $lineTotal,
        int $discount,
        int $profitTotal
    ): Transaction {
        $transaction = Transaction::create([
            'cashier_id' => $cashier->id,
            'customer_id' => $customer->id,
            'invoice' => $invoice,
            'cash' => $lineTotal,
            'change' => 0,
            'discount' => $discount,
            'grand_total' => $lineTotal - $discount,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $transaction->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        $transaction->details()->create([
            'product_id' => $product->id,
            'qty' => $quantity,
            'base_unit_price' => (int) round($lineTotal / $quantity),
            'unit_price' => (int) round($lineTotal / $quantity),
            'price' => $lineTotal,
        ]);
        $transaction->profits()->create([
            'total' => $profitTotal,
        ]);

        return $transaction;
    }
}
