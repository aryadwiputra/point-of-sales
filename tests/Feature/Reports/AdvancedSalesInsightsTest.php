<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerCampaign;
use App\Models\CustomerCampaignLog;
use App\Models\CustomerSegment;
use App\Models\CustomerVoucher;
use App\Models\LoyaltyPointHistory;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdvancedSalesInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate([
            'name' => 'reports-access',
            'guard_name' => 'web',
        ]);
    }

    public function test_authorized_user_can_open_advanced_sales_insights_page(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('reports.insights.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Reports/Insights')
                ->has('summary')
                ->has('salesByHour')
                ->has('salesByDay')
                ->has('topSellingProducts')
                ->has('lowPerformingProducts')
                ->has('marginByProduct')
                ->has('marginByCategory')
                ->has('cashierPerformance')
            );
    }

    public function test_insights_use_qty_sold_for_top_selling_and_include_unsold_stocked_products_in_low_performing(): void
    {
        $user = $this->createUser();
        $category = Category::create([
            'name' => 'Insights Category',
            'description' => 'Testing',
            'image' => 'category.png',
        ]);
        $customer = Customer::create([
            'name' => 'Insight Customer',
            'no_telp' => '62811111111',
            'address' => 'Jl. Insight',
        ]);
        $cashier = User::factory()->create();

        $topProduct = $this->createProduct($category, 'Top Product', 'TOP-001', 12_000, 20_000, 10);
        $revenueProduct = $this->createProduct($category, 'Revenue Product', 'REV-001', 30_000, 50_000, 8);
        $unsoldProduct = $this->createProduct($category, 'Unsold Product', 'UNS-001', 15_000, 25_000, 6);

        $this->createTransactionWithDetails($cashier, $customer, Carbon::parse('2026-05-05 09:10:00'), [
            ['product' => $topProduct, 'qty' => 5, 'line_total' => 100_000],
            ['product' => $revenueProduct, 'qty' => 1, 'line_total' => 50_000],
        ]);

        $response = $this->actingAs($user)->get(route('reports.insights.index'));
        $response->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];

            $this->assertSame('Top Product', $props['topSellingProducts'][0]['product_title']);
            $this->assertSame(5, $props['topSellingProducts'][0]['qty_sold']);
            $this->assertSame('Unsold Product', $props['lowPerformingProducts'][0]['product_title']);
            $this->assertSame(0, $props['lowPerformingProducts'][0]['qty_sold']);
        });
    }

    public function test_insights_filters_affect_sales_by_day_and_cashier_performance(): void
    {
        $user = $this->createUser();
        $category = Category::create([
            'name' => 'Insights Category',
            'description' => 'Testing',
            'image' => 'category.png',
        ]);
        $customer = Customer::create([
            'name' => 'Insight Customer',
            'no_telp' => '62812222222',
            'address' => 'Jl. Insight 2',
        ]);
        $cashierA = User::factory()->create(['name' => 'Cashier A']);
        $cashierB = User::factory()->create(['name' => 'Cashier B']);
        $product = $this->createProduct($category, 'Filter Product', 'FLT-001', 10_000, 20_000, 10);

        $this->createTransactionWithDetails($cashierA, $customer, Carbon::parse('2026-05-01 08:00:00'), [
            ['product' => $product, 'qty' => 2, 'line_total' => 40_000],
        ]);
        $this->createTransactionWithDetails($cashierB, $customer, Carbon::parse('2026-05-02 14:30:00'), [
            ['product' => $product, 'qty' => 1, 'line_total' => 20_000],
        ]);

        $response = $this->actingAs($user)->get(route('reports.insights.index', [
            'start_date' => '2026-05-02',
            'end_date' => '2026-05-02',
            'cashier_id' => $cashierB->id,
        ]));

        $response->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];

            $this->assertCount(1, $props['salesByDay']);
            $this->assertSame('02 May', $props['salesByDay'][0]['label']);
            $this->assertSame(20_000, $props['salesByDay'][0]['revenue_total']);
            $this->assertCount(1, $props['cashierPerformance']);
            $this->assertSame('Cashier B', $props['cashierPerformance'][0]['cashier_name']);
            $this->assertSame(1, $props['cashierPerformance'][0]['orders_count']);
        });
    }

    public function test_sales_by_hour_uses_hour_buckets(): void
    {
        $user = $this->createUser();
        $category = Category::create([
            'name' => 'Insights Category',
            'description' => 'Testing',
            'image' => 'category.png',
        ]);
        $customer = Customer::create([
            'name' => 'Insight Customer',
            'no_telp' => '62813333333',
            'address' => 'Jl. Insight 3',
        ]);
        $cashier = User::factory()->create();
        $product = $this->createProduct($category, 'Hourly Product', 'HR-001', 8_000, 15_000, 9);

        $this->createTransactionWithDetails($cashier, $customer, Carbon::parse('2026-05-03 09:15:00'), [
            ['product' => $product, 'qty' => 1, 'line_total' => 15_000],
        ]);
        $this->createTransactionWithDetails($cashier, $customer, Carbon::parse('2026-05-03 17:45:00'), [
            ['product' => $product, 'qty' => 2, 'line_total' => 30_000],
        ]);

        $response = $this->actingAs($user)->get(route('reports.insights.index'));
        $response->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];
            $bucket09 = collect($props['salesByHour'])->firstWhere('hour', 9);
            $bucket17 = collect($props['salesByHour'])->firstWhere('hour', 17);

            $this->assertSame(15_000, $bucket09['revenue_total']);
            $this->assertSame(30_000, $bucket17['revenue_total']);
        });
    }

    public function test_repeat_customer_metrics_identify_repeat_customers_and_member_share(): void
    {
        $user = $this->createUser();
        $category = Category::create([
            'name' => 'Insights Category',
            'description' => 'Testing',
            'image' => 'category.png',
        ]);
        $repeatMember = Customer::create([
            'name' => 'Repeat Member',
            'no_telp' => '62814444444',
            'address' => 'Jl. Repeat 1',
            'is_loyalty_member' => true,
            'loyalty_tier' => 'gold',
        ]);
        $singleCustomer = Customer::create([
            'name' => 'Single Customer',
            'no_telp' => '62815555555',
            'address' => 'Jl. Repeat 2',
        ]);
        $cashier = User::factory()->create();
        $product = $this->createProduct($category, 'Repeat Product', 'RPT-001', 10_000, 20_000, 12);

        $this->createTransactionWithDetails($cashier, $repeatMember, Carbon::parse('2026-05-01 10:00:00'), [
            ['product' => $product, 'qty' => 1, 'line_total' => 20_000],
        ]);
        $this->createTransactionWithDetails($cashier, $repeatMember, Carbon::parse('2026-05-02 10:00:00'), [
            ['product' => $product, 'qty' => 2, 'line_total' => 40_000],
        ]);
        $this->createTransactionWithDetails($cashier, $singleCustomer, Carbon::parse('2026-05-03 10:00:00'), [
            ['product' => $product, 'qty' => 1, 'line_total' => 20_000],
        ]);

        $response = $this->actingAs($user)->get(route('reports.insights.index'));
        $response->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];

            $this->assertSame(2, $props['repeatCustomerMetrics']['summary']['active_customers']);
            $this->assertSame(1, $props['repeatCustomerMetrics']['summary']['repeat_customers']);
            $this->assertSame(1, $props['repeatCustomerMetrics']['summary']['new_customers']);
            $this->assertEquals(75.0, $props['repeatCustomerMetrics']['summary']['member_revenue_share']);
            $this->assertSame('Repeat Member', $props['repeatCustomerMetrics']['top_customers'][0]['customer_name']);
            $this->assertSame(2, $props['repeatCustomerMetrics']['top_customers'][0]['orders_count']);
        });
    }

    public function test_stock_coverage_analysis_calculates_daily_velocity_and_no_movement_products(): void
    {
        $user = $this->createUser();
        $category = Category::create([
            'name' => 'Insights Category',
            'description' => 'Testing',
            'image' => 'category.png',
        ]);
        $customer = Customer::create([
            'name' => 'Coverage Customer',
            'no_telp' => '62816666666',
            'address' => 'Jl. Coverage',
        ]);
        $cashier = User::factory()->create();
        $fastProduct = $this->createProduct($category, 'Fast Product', 'FST-001', 10_000, 20_000, 4);
        $steadyProduct = $this->createProduct($category, 'Steady Product', 'STD-001', 10_000, 20_000, 20);
        $idleProduct = $this->createProduct($category, 'Idle Product', 'IDL-001', 10_000, 20_000, 12);

        $this->createTransactionWithDetails($cashier, $customer, Carbon::parse('2026-05-01 11:00:00'), [
            ['product' => $fastProduct, 'qty' => 4, 'line_total' => 80_000],
            ['product' => $steadyProduct, 'qty' => 2, 'line_total' => 40_000],
        ]);
        $this->createTransactionWithDetails($cashier, $customer, Carbon::parse('2026-05-05 11:00:00'), [
            ['product' => $steadyProduct, 'qty' => 3, 'line_total' => 60_000],
        ]);

        $response = $this->actingAs($user)->get(route('reports.insights.index', [
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-05',
        ]));

        $response->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];
            $fastProduct = collect($props['stockCoverage']['products'])->firstWhere('product_title', 'Fast Product');
            $idleProduct = collect($props['stockCoverage']['products'])->firstWhere('product_title', 'Idle Product');

            $this->assertSame(5, $props['stockCoverage']['summary']['window_days']);
            $this->assertSame('critical', $fastProduct['coverage_status']);
            $this->assertEquals(0.8, $fastProduct['average_daily_qty']);
            $this->assertEquals(5.0, $fastProduct['coverage_days']);
            $this->assertSame('no_movement', $idleProduct['coverage_status']);
        });
    }

    public function test_wave_three_summaries_include_promo_loyalty_and_crm_operational_metrics(): void
    {
        $user = $this->createUser();
        $category = Category::create([
            'name' => 'Promo Category',
            'description' => 'Testing',
            'image' => 'category.png',
        ]);
        $member = Customer::create([
            'name' => 'Loyal Member',
            'no_telp' => '62817777777',
            'address' => 'Jl. Loyalty',
            'is_loyalty_member' => true,
            'loyalty_tier' => 'gold',
            'loyalty_points' => 120,
            'loyalty_total_spent' => 1_500_000,
            'loyalty_transaction_count' => 6,
        ]);
        $product = $this->createProduct($category, 'Promo Product', 'PRM-001', 10_000, 20_000, 10);

        PricingRule::create([
            'name' => 'Active Promo',
            'kind' => PricingRule::KIND_STANDARD_DISCOUNT,
            'is_active' => true,
            'priority' => 100,
            'target_type' => PricingRule::TARGET_PRODUCT,
            'product_id' => $product->id,
            'customer_scope' => PricingRule::SCOPE_ALL,
            'discount_type' => PricingRule::TYPE_PERCENTAGE,
            'discount_value' => 10,
        ]);

        PricingRule::create([
            'name' => 'Scheduled Promo',
            'kind' => PricingRule::KIND_BUNDLE_PRICE,
            'is_active' => true,
            'priority' => 80,
            'target_type' => PricingRule::TARGET_CATEGORY,
            'category_id' => $category->id,
            'customer_scope' => PricingRule::SCOPE_MEMBER,
            'discount_type' => PricingRule::TYPE_FIXED_PRICE,
            'discount_value' => 50_000,
            'starts_at' => now()->addDays(2),
        ]);

        \App\Models\AuditLog::create([
            'event' => 'pricing_rule.created',
            'module' => 'pricing_rules',
            'description' => 'Rule promo dibuat.',
            'created_at' => now(),
        ]);

        LoyaltyPointHistory::create([
            'customer_id' => $member->id,
            'type' => LoyaltyPointHistory::TYPE_EARN,
            'points_delta' => 30,
            'balance_after' => 150,
            'amount_delta' => 300_000,
        ]);

        LoyaltyPointHistory::create([
            'customer_id' => $member->id,
            'type' => LoyaltyPointHistory::TYPE_REDEEM,
            'points_delta' => -10,
            'balance_after' => 140,
            'amount_delta' => 10_000,
        ]);

        CustomerVoucher::create([
            'customer_id' => $member->id,
            'code' => 'VC-ACTIVE',
            'name' => 'Voucher Aktif',
            'discount_type' => CustomerVoucher::TYPE_FIXED_AMOUNT,
            'discount_value' => 10_000,
            'minimum_order' => 50_000,
            'is_active' => true,
            'is_used' => false,
        ]);

        CustomerVoucher::create([
            'customer_id' => $member->id,
            'code' => 'VC-USED',
            'name' => 'Voucher Used',
            'discount_type' => CustomerVoucher::TYPE_FIXED_AMOUNT,
            'discount_value' => 10_000,
            'minimum_order' => 50_000,
            'is_active' => true,
            'is_used' => true,
            'used_at' => now(),
        ]);

        $manualSegment = CustomerSegment::create([
            'name' => 'Manual Segment',
            'slug' => 'manual-segment',
            'type' => CustomerSegment::TYPE_MANUAL,
            'is_active' => true,
        ]);
        $autoSegment = CustomerSegment::create([
            'name' => 'Auto Segment',
            'slug' => 'auto-segment',
            'type' => CustomerSegment::TYPE_AUTO,
            'is_active' => true,
            'auto_rule_type' => CustomerSegment::RULE_SPENDING,
            'rule_config' => [],
        ]);
        $manualSegment->memberships()->create([
            'customer_id' => $member->id,
            'source' => 'manual',
            'matched_at' => now(),
        ]);
        $autoSegment->memberships()->create([
            'customer_id' => $member->id,
            'source' => 'auto',
            'matched_at' => now(),
        ]);

        $campaign = CustomerCampaign::create([
            'name' => 'Promo CRM',
            'type' => CustomerCampaign::TYPE_PROMO_BROADCAST,
            'status' => CustomerCampaign::STATUS_READY,
            'channel' => CustomerCampaign::CHANNEL_WHATSAPP_LINK,
            'message_template' => 'Halo',
            'created_by' => $user->id,
        ]);

        CustomerCampaignLog::create([
            'customer_campaign_id' => $campaign->id,
            'customer_id' => $member->id,
            'channel' => CustomerCampaign::CHANNEL_WHATSAPP_LINK,
            'status' => CustomerCampaignLog::STATUS_READY_TO_SEND,
            'payload' => ['phone' => $member->no_telp],
        ]);

        $response = $this->actingAs($user)->get(route('reports.insights.index'));

        $response->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];

            $this->assertSame(1, $props['promoMonitor']['summary']['active']);
            $this->assertSame(1, $props['promoMonitor']['summary']['scheduled']);
            $this->assertSame(1, $props['loyaltyPerformance']['summary']['total_members']);
            $this->assertSame(30, $props['loyaltyPerformance']['summary']['points_earned']);
            $this->assertSame(10, $props['loyaltyPerformance']['summary']['points_redeemed']);
            $this->assertSame(1, $props['loyaltyPerformance']['summary']['voucher_summary']['active']);
            $this->assertSame(1, $props['loyaltyPerformance']['summary']['voucher_summary']['used']);
            $this->assertSame(2, $props['crmOperations']['summary']['segments_total']);
            $this->assertSame(1, $props['crmOperations']['summary']['campaigns_ready']);
            $this->assertSame(1, $props['crmOperations']['summary']['queue_ready_to_send']);
        });
    }

    private function createUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('reports-access');

        return $user;
    }

    private function createProduct(
        Category $category,
        string $title,
        string $sku,
        int $buyPrice,
        int $sellPrice,
        int $stock
    ): Product {
        return Product::create([
            'image' => 'product.png',
            'barcode' => $sku,
            'sku' => $sku,
            'title' => $title,
            'description' => $title,
            'buy_price' => $buyPrice,
            'sell_price' => $sellPrice,
            'category_id' => $category->id,
            'stock' => $stock,
            'tax_rate' => 0,
        ]);
    }

    private function createTransactionWithDetails(User $cashier, Customer $customer, Carbon $createdAt, array $lines): Transaction
    {
        $subtotal = collect($lines)->sum('line_total');
        $transaction = Transaction::create([
            'cashier_id' => $cashier->id,
            'customer_id' => $customer->id,
            'invoice' => 'TRX-'.strtoupper((string) str()->random(8)),
            'cash' => $subtotal,
            'change' => 0,
            'discount' => 0,
            'grand_total' => $subtotal,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $transaction->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        foreach ($lines as $line) {
            $product = $line['product'];
            $quantity = $line['qty'];
            $lineTotal = $line['line_total'];

            $detail = $transaction->details()->create([
                'product_id' => $product->id,
                'qty' => $quantity,
                'base_unit_price' => $product->sell_price,
                'unit_price' => (int) round($lineTotal / $quantity),
                'price' => $lineTotal,
            ]);
            $detail->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->saveQuietly();

            $profit = $transaction->profits()->create([
                'total' => $lineTotal - ($product->buy_price * $quantity),
            ]);
            $profit->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->saveQuietly();
        }

        return $transaction;
    }
}
