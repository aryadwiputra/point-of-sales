<?php

namespace Tests\Feature\Billing;

use App\Models\Category;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use App\Services\CheckoutService;
use App\Services\TenantContext;
use App\Services\TransactionFeeService;
use App\Support\Checkout\CheckoutContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paket "Bayar Per Transaksi" (billing_type = per_transaction):
 * perhitungan biaya di server, pencatatan saat checkout, dan gating fitur inti.
 */
class TransactionFeeTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected Plan $plan;

    protected Tenant $tenant;

    protected Warehouse $warehouse;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        // User dicari SEBELUM TenantContext aktif (User tenant-scoped).
        $this->cashier = User::where('email', 'cashier@gmail.com')->first();
        $this->cashier->markEmailAsVerified();

        $this->plan = Plan::create([
            'name' => 'Bayar Per Transaksi',
            'slug' => 'pay-per-transaction-test',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'billing_type' => 'per_transaction',
            'fee_type' => 'percent',
            'fee_value' => 1.5,
            'fee_min' => 500,
            'fee_max' => 2000,
            'fee_rounding' => 'none',
            'fee_methods' => null,
            'max_outlets' => 1,
            'max_users' => 2,
            'max_products' => 500,
            'max_customers' => 1000,
            'features' => ['basic_pos', 'cashier_shift', 'sales_report'],
            'is_active' => true,
        ]);

        $this->tenant = Tenant::create([
            'name' => 'Warung Uji Biaya',
            'subdomain' => 'biayatest',
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        // Sinkronkan tenant_id cashier agar terlihat oleh query bercakup tenant.
        $this->cashier->update(['tenant_id' => $this->tenant->id]);

        // Data operasional dibuat SETELAH context aktif agar ter-stamp tenant
        // dan terlihat oleh query bercakup tenant saat checkout.
        $category = Category::create([
            'name' => 'Kategori Biaya',
            'image' => 'categories/fee.jpg',
            'description' => 'Kategori untuk test biaya transaksi',
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->product = Product::create([
            'title' => 'Produk Biaya',
            'sku' => 'SKU-FEE-'.uniqid(),
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 100,
            'image' => 'products/fee.jpg',
            'barcode' => 'BC-FEE-'.uniqid(),
            'description' => 'Produk untuk test biaya transaksi',
            'tax_rate' => 0,
            'category_id' => $category->id,
        ]);
        $this->warehouse->products()->attach($this->product->id, ['stock' => 100]);

        app(CashierShiftService::class)->openShift(
            $this->cashier,
            $this->cashier,
            0,
            null,
            $this->warehouse->id,
        );

        $this->actingAs($this->cashier);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** Ubah konfigurasi paket lalu buang cache relasi plan pada context. */
    private function updatePlan(array $attributes): void
    {
        $this->plan->update($attributes);
        $this->tenant->unsetRelation('plan');
    }

    private function feeService(): TransactionFeeService
    {
        return app(TransactionFeeService::class);
    }

    private function cashCheckout(): CheckoutContext
    {
        $this->post(route('transactions.addToCart'), [
            'product_id' => $this->product->id,
            'sell_price' => 10000,
            'qty' => 1,
        ]);

        return new CheckoutContext(
            userId: $this->cashier->id,
            customer: null,
            voucher: null,
            manualDiscount: 0,
            shippingCost: 0,
            requestedRedeemPoints: 0,
            isPayLater: false,
            dueDate: null,
            orderType: null,
            note: null,
            customerNpwp: null,
            isCashPayment: true,
            cashAmount: 10000,
            paymentGateway: null,
            useTenders: false,
            tenderInput: [],
            outlet: null,
            bankAccountId: null,
        );
    }

    public function test_percent_fee_honors_min_and_max_caps(): void
    {
        $fee = $this->feeService();

        // 1,5% dari Rp5.000 = Rp75 → dinaikkan ke minimum Rp500.
        $this->assertSame(500, $fee->calculate(5000, ['cash']));

        // 1,5% dari Rp50.000 = Rp750 → dipakai apa adanya.
        $this->assertSame(750, $fee->calculate(50000, ['cash']));

        // 1,5% dari Rp1.000.000 = Rp15.000 → dijepit ke maksimum Rp2.000.
        $this->assertSame(2000, $fee->calculate(1000000, ['cash']));
    }

    public function test_fixed_fee_and_rounding_modes(): void
    {
        $this->updatePlan([
            'fee_type' => 'fixed',
            'fee_value' => 1000,
            'fee_min' => 0,
            'fee_max' => null,
        ]);
        $this->assertSame(1000, $this->feeService()->calculate(5000, ['cash']));

        // 1,5% dari Rp9.991 = Rp149,865
        $this->updatePlan([
            'fee_type' => 'percent',
            'fee_value' => 1.5,
            'fee_min' => 0,
            'fee_max' => null,
            'fee_rounding' => 'down',
        ]);
        $this->assertSame(149, $this->feeService()->calculate(9991, ['cash']));

        $this->updatePlan(['fee_rounding' => 'up']);
        $this->assertSame(150, $this->feeService()->calculate(9991, ['cash']));

        $this->updatePlan(['fee_rounding' => 'none']);
        $this->assertSame(150, $this->feeService()->calculate(9991, ['cash']));
    }

    public function test_fee_only_applies_to_configured_payment_methods(): void
    {
        $this->updatePlan(['fee_methods' => ['qris']]);

        $this->assertSame(0, $this->feeService()->calculate(50000, ['cash']));
        $this->assertSame(750, $this->feeService()->calculate(50000, ['qris']));

        // Split: salah satu metode kena biaya → biaya tetap dihitung.
        $this->assertSame(750, $this->feeService()->calculate(50000, ['cash', 'qris']));
    }

    public function test_subscription_plan_has_no_transaction_fee(): void
    {
        $this->updatePlan([
            'billing_type' => 'subscription',
            'price_monthly' => 99000,
            'price_yearly' => 990000,
        ]);

        $this->assertNull($this->feeService()->config());
        $this->assertSame(0, $this->feeService()->calculate(1000000, ['cash']));
    }

    public function test_checkout_records_service_fee_without_changing_grand_total(): void
    {
        $result = app(CheckoutService::class)->execute($this->cashCheckout());

        // 1,5% dari Rp10.000 = Rp150 → minimum Rp500.
        $this->assertSame(10000, $result->transaction->grand_total);
        $this->assertSame(500, (int) $result->transaction->service_fee);
        $this->assertSame(10000, (int) $result->transaction->cash);
    }

    public function test_checkout_records_zero_fee_for_subscription_tenant(): void
    {
        $this->updatePlan([
            'billing_type' => 'subscription',
            'price_monthly' => 99000,
            'price_yearly' => 990000,
        ]);

        $result = app(CheckoutService::class)->execute($this->cashCheckout());

        $this->assertSame(10000, $result->transaction->grand_total);
        $this->assertSame(0, (int) $result->transaction->service_fee);
    }

    public function test_per_transaction_plan_only_opens_core_pos_features(): void
    {
        $this->assertTrue($this->plan->isPerTransaction());
        $this->assertTrue($this->plan->hasFeature('basic_pos'));
        $this->assertTrue($this->plan->hasFeature('cashier_shift'));
        $this->assertTrue($this->plan->hasFeature('sales_report'));

        // Fitur bernilai tinggi & multi-cabang tetap terkunci.
        $this->assertFalse($this->plan->hasFeature('multi_warehouse'));
        $this->assertFalse($this->plan->hasFeature('purchase_order'));
        $this->assertFalse($this->plan->hasFeature('crm'));
        $this->assertFalse($this->plan->hasFeature('loyalty'));
        $this->assertFalse($this->plan->hasFeature('multi_wa'));
        $this->assertFalse($this->plan->hasFeature('finance'));
        $this->assertFalse($this->plan->hasFeature('bihaju'));
        $this->assertFalse($this->plan->hasFeature('api_access'));
        $this->assertFalse($this->plan->hasFeature('custom_domain'));
    }
}
