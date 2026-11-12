<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperadminPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected User $platformAdmin;

    protected User $tenantAdmin;

    protected Tenant $tenant;

    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->plan = Plan::where('slug', 'pro')->first();

        // Platform Superadmin (tenant_id = null)
        $this->platformAdmin = User::factory()->create([
            'name' => 'Platform Superadmin',
            'email' => 'superadmin@platform.test',
            'tenant_id' => null,
        ]);
        $this->platformAdmin->assignRole('super-admin');

        // Normal Tenant & Tenant Admin (tenant_id = $tenant->id)
        $this->tenant = Tenant::create([
            'name' => 'Toko Maju Terus',
            'subdomain' => 'tokomaju',
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'subscription_ends_at' => now()->addDays(30),
        ]);

        $this->tenantAdmin = User::factory()->create([
            'name' => 'Toko Admin',
            'email' => 'admin@tokomaju.test',
            'tenant_id' => $this->tenant->id,
        ]);
        $this->tenantAdmin->assignRole('super-admin');

        $this->tenant->update(['owner_id' => $this->tenantAdmin->id]);

        TenantContext::reset();
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_unauthenticated_user_redirected_from_superadmin(): void
    {
        $response = $this->get(route('superadmin.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_tenant_admin_forbidden_from_superadmin(): void
    {
        $response = $this->actingAs($this->tenantAdmin)
            ->get(route('superadmin.dashboard'));

        $response->assertForbidden();
    }

    public function test_platform_superadmin_can_access_dashboard(): void
    {
        $response = $this->actingAs($this->platformAdmin)
            ->get(route('superadmin.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Superadmin/Dashboard')
            ->has('metrics')
            ->has('plans')
            ->has('recentTenants')
        );
    }

    public function test_platform_superadmin_can_view_tenants_and_detail(): void
    {
        // 1. Index
        $response = $this->actingAs($this->platformAdmin)
            ->get(route('superadmin.tenants.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Superadmin/Tenants/Index')
            ->has('tenants.data')
        );

        // 2. Show
        $response = $this->actingAs($this->platformAdmin)
            ->get(route('superadmin.tenants.show', $this->tenant->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Superadmin/Tenants/Show')
            ->where('tenant.id', $this->tenant->id)
            ->has('stats')
        );
    }

    public function test_platform_superadmin_can_change_tenant_status(): void
    {
        $response = $this->actingAs($this->platformAdmin)
            ->patch(route('superadmin.tenants.status', $this->tenant->id), [
                'status' => 'suspended',
            ]);

        $response->assertSessionHas('success');
        $this->assertEquals('suspended', $this->tenant->fresh()->status);
    }

    public function test_platform_superadmin_can_extend_subscription(): void
    {
        $originalEnd = $this->tenant->subscription_ends_at;

        $response = $this->actingAs($this->platformAdmin)
            ->post(route('superadmin.tenants.extend', $this->tenant->id), [
                'days' => 60,
            ]);

        $response->assertSessionHas('success');
        $freshTenant = $this->tenant->fresh();
        $this->assertTrue($freshTenant->subscription_ends_at->isAfter($originalEnd));
        $this->assertEquals('active', $freshTenant->status);
    }

    public function test_platform_superadmin_can_change_tenant_plan(): void
    {
        $starterPlan = Plan::where('slug', 'starter')->first();

        $response = $this->actingAs($this->platformAdmin)
            ->patch(route('superadmin.tenants.plan', $this->tenant->id), [
                'plan_id' => $starterPlan->id,
            ]);

        $response->assertSessionHas('success');
        $this->assertEquals($starterPlan->id, $this->tenant->fresh()->plan_id);
    }

    public function test_platform_superadmin_can_update_plan_limits(): void
    {
        $response = $this->actingAs($this->platformAdmin)
            ->put(route('superadmin.plans.update', $this->plan->id), [
                'name' => 'Pro Updated',
                'price_monthly' => 299000,
                'price_yearly' => 2990000,
                'max_outlets' => 5,
                'max_users' => 15,
                'max_products' => 8000,
                'max_customers' => 10000,
                'is_active' => true,
            ]);

        $response->assertSessionHas('success');
        $freshPlan = $this->plan->fresh();
        $this->assertEquals('Pro Updated', $freshPlan->name);
        $this->assertEquals(5, $freshPlan->max_outlets);
        $this->assertEquals(15, $freshPlan->max_users);
    }

    public function test_platform_superadmin_can_configure_transaction_fee(): void
    {
        $response = $this->actingAs($this->platformAdmin)
            ->put(route('superadmin.plans.update', $this->plan->id), [
                'name' => 'Pro',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'max_outlets' => 3,
                'max_users' => 10,
                'max_products' => 5000,
                'max_customers' => 10000,
                'is_active' => true,
                'billing_type' => 'per_transaction',
                'fee_type' => 'percent',
                'fee_value' => 1.5,
                'fee_min' => 500,
                'fee_max' => 2000,
                'fee_rounding' => 'down',
                'fee_methods' => ['qris', 'bank_transfer'],
            ]);

        $response->assertSessionHas('success');
        $freshPlan = $this->plan->fresh();
        $this->assertSame('per_transaction', $freshPlan->billing_type);
        $this->assertSame('percent', $freshPlan->fee_type);
        $this->assertEquals(1.5, (float) $freshPlan->fee_value);
        $this->assertEquals(500, (int) $freshPlan->fee_min);
        $this->assertEquals(2000, (int) $freshPlan->fee_max);
        $this->assertSame('down', $freshPlan->fee_rounding);
        $this->assertSame(['qris', 'bank_transfer'], $freshPlan->fee_methods);
    }

    public function test_platform_superadmin_rejects_invalid_fee_settings(): void
    {
        $response = $this->actingAs($this->platformAdmin)
            ->put(route('superadmin.plans.update', $this->plan->id), [
                'name' => 'Pro',
                'price_monthly' => 249000,
                'price_yearly' => 2490000,
                'max_outlets' => 3,
                'max_users' => 10,
                'max_products' => 5000,
                'max_customers' => 10000,
                'is_active' => true,
                'billing_type' => 'per_transaction',
                'fee_type' => 'uang_ajaib',
            ]);

        $response->assertSessionHasErrors('fee_type');
        $this->assertSame('subscription', $this->plan->fresh()->billing_type);
    }

    public function test_switching_back_to_subscription_clears_fee_config(): void
    {
        $this->plan->update([
            'billing_type' => 'per_transaction',
            'fee_type' => 'fixed',
            'fee_value' => 1000,
            'fee_min' => 500,
            'fee_max' => 2000,
            'fee_methods' => ['qris'],
        ]);

        $response = $this->actingAs($this->platformAdmin)
            ->put(route('superadmin.plans.update', $this->plan->id), [
                'name' => 'Pro',
                'price_monthly' => 249000,
                'price_yearly' => 2490000,
                'max_outlets' => 3,
                'max_users' => 10,
                'max_products' => 5000,
                'max_customers' => 10000,
                'is_active' => true,
                'billing_type' => 'subscription',
            ]);

        $response->assertSessionHas('success');
        $freshPlan = $this->plan->fresh();
        $this->assertSame('subscription', $freshPlan->billing_type);
        $this->assertNull($freshPlan->fee_type);
        $this->assertEquals(0, (float) $freshPlan->fee_value);
        $this->assertNull($freshPlan->fee_max);
        $this->assertNull($freshPlan->fee_methods);
    }

    public function test_seeded_pay_per_transaction_plan_opens_core_pos_only(): void
    {
        $plan = Plan::where('slug', 'pay-per-transaction')->first();

        $this->assertNotNull($plan);
        $this->assertSame('per_transaction', $plan->billing_type);
        $this->assertEquals(1.5, (float) $plan->fee_value);
        $this->assertEquals(500, (int) $plan->fee_min);
        $this->assertEquals(2000, (int) $plan->fee_max);
        $this->assertEquals(0, (float) $plan->price_monthly);

        $this->assertTrue($plan->hasFeature('basic_pos'));
        $this->assertTrue($plan->hasFeature('cashier_shift'));
        $this->assertTrue($plan->hasFeature('sales_report'));

        $this->assertFalse($plan->hasFeature('multi_warehouse'));
        $this->assertFalse($plan->hasFeature('purchase_order'));
        $this->assertFalse($plan->hasFeature('crm'));
        $this->assertFalse($plan->hasFeature('multi_wa'));
        $this->assertFalse($plan->hasFeature('finance'));
        $this->assertFalse($plan->hasFeature('api_access'));
        $this->assertFalse($plan->hasFeature('custom_domain'));
    }

    public function test_platform_superadmin_can_monitor_transactions_across_tenants(): void
    {
        $secondTenant = Tenant::create([
            'name' => 'Toko Kedua',
            'subdomain' => 'tokokedua',
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'subscription_ends_at' => now()->addDays(30),
        ]);

        $secondCashier = User::factory()->create([
            'name' => 'Kasir Kedua',
            'email' => 'kasir@tokokedua.test',
            'tenant_id' => $secondTenant->id,
        ]);

        $makeTransaction = function (int $tenantId, int $cashierId, string $invoice, int $grandTotal) {
            return Transaction::create([
                'tenant_id' => $tenantId,
                'cashier_id' => $cashierId,
                'invoice' => $invoice,
                'cash' => $grandTotal,
                'change' => 0,
                'discount' => 0,
                'grand_total' => $grandTotal,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
            ]);
        };

        $makeTransaction($this->tenant->id, $this->tenantAdmin->id, 'INV-T1-001', 150000);
        $makeTransaction($secondTenant->id, $secondCashier->id, 'INV-T2-001', 250000);

        TenantContext::reset();

        $response = $this->actingAs($this->platformAdmin)
            ->get(route('superadmin.transactions.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Superadmin/Transactions/Index')
            ->has('transactions.data', 2)
            ->has('monthly', 6)
            ->has('tenants')
            ->where('summary.transactions_count', 2)
            ->where('summary.revenue_total', 400000)
            ->where('summary.tenants_transacting', 2)
        );
    }

    public function test_platform_transaction_monitoring_filters_by_tenant(): void
    {
        $makeTransaction = function (int $tenantId, string $invoice, int $grandTotal) {
            return Transaction::create([
                'tenant_id' => $tenantId,
                'cashier_id' => $this->tenantAdmin->id,
                'invoice' => $invoice,
                'cash' => $grandTotal,
                'change' => 0,
                'discount' => 0,
                'grand_total' => $grandTotal,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
            ]);
        };

        $makeTransaction($this->tenant->id, 'INV-FILTER-001', 100000);

        TenantContext::reset();

        $response = $this->actingAs($this->platformAdmin)
            ->get(route('superadmin.transactions.index', [
                'tenant_id' => $this->tenant->id,
                'search' => 'INV-FILTER',
            ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.invoice', 'INV-FILTER-001')
            ->where('summary.revenue_total', 100000)
        );
    }

    public function test_tenant_admin_cannot_access_platform_transactions(): void
    {
        $response = $this->actingAs($this->tenantAdmin)
            ->get(route('superadmin.transactions.index'));

        $response->assertForbidden();
    }
}
