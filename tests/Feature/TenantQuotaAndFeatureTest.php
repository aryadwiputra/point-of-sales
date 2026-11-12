<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuotaService;
use App\Services\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantQuotaAndFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $starterTenant;
    protected Tenant $proTenant;
    protected User $starterAdmin;
    protected User $proAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        // Custom Starter Plan with strict limits for testing
        $starterPlan = Plan::create([
            'name' => 'Starter Test',
            'slug' => 'starter-test',
            'price_monthly' => 99000,
            'price_yearly' => 990000,
            'max_outlets' => 1,
            'max_users' => 2,
            'max_products' => 3,
            'max_customers' => 5,
            'features' => ['basic_pos', 'cashier_shift'],
            'is_active' => true,
        ]);

        $proPlan = Plan::where('slug', 'pro')->first();

        $this->starterTenant = Tenant::create([
            'name' => 'Warung Starter',
            'subdomain' => 'startertest',
            'plan_id' => $starterPlan->id,
            'status' => 'active',
        ]);

        $this->proTenant = Tenant::create([
            'name' => 'Toko Pro',
            'subdomain' => 'protest',
            'plan_id' => $proPlan->id,
            'status' => 'active',
        ]);

        $this->starterAdmin = User::factory()->create([
            'name' => 'Admin Starter',
            'email' => 'admin@starter.test',
            'tenant_id' => $this->starterTenant->id,
        ]);
        $this->starterAdmin->assignRole('super-admin');

        $this->proAdmin = User::factory()->create([
            'name' => 'Admin Pro',
            'email' => 'admin@pro.test',
            'tenant_id' => $this->proTenant->id,
        ]);
        $this->proAdmin->assignRole('super-admin');

        TenantContext::reset();
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_quota_service_detects_limits_accurately(): void
    {
        TenantContext::set($this->starterTenant);
        $quota = app(QuotaService::class);

        // Outlet limit: 1
        $this->assertTrue($quota->canCreateOutlet());
        Outlet::create([
            'code' => 'OUT-1',
            'name' => 'Outlet Pertama',
        ]);
        $this->assertFalse($quota->canCreateOutlet());

        // User limit: 2 (starterAdmin already counted as 1)
        $this->assertTrue($quota->canAddUser());
        User::factory()->create(['tenant_id' => $this->starterTenant->id]);
        $this->assertFalse($quota->canAddUser());

        // Product limit: 3
        $category = Category::create(['name' => 'Umum']);
        $this->assertTrue($quota->canCreateProduct());
        for ($i = 1; $i <= 3; $i++) {
            Product::create([
                'barcode' => "BC-{$i}",
                'sku' => "SKU-{$i}",
                'title' => "Produk {$i}",
                'category_id' => $category->id,
                'buy_price' => 1000,
                'sell_price' => 2000,
                'stock' => 10,
                'tax_rate' => 0,
            ]);
        }
        $this->assertFalse($quota->canCreateProduct());

        // Features
        $this->assertTrue($quota->hasFeature('basic_pos'));
        $this->assertFalse($quota->hasFeature('crm'));
        $this->assertFalse($quota->hasFeature('multi_warehouse'));

        // Usage Summary
        $summary = $quota->getUsageSummary();
        $this->assertEquals(1, $summary['outlets']['current']);
        $this->assertEquals(1, $summary['outlets']['max']);
        $this->assertFalse($summary['outlets']['can_create']);
        $this->assertEquals(3, $summary['products']['current']);
        $this->assertEquals(3, $summary['products']['max']);
        $this->assertFalse($summary['products']['can_create']);
    }

    public function test_product_store_route_blocks_when_quota_exceeded(): void
    {
        TenantContext::set($this->starterTenant);
        $category = Category::create(['name' => 'Umum']);

        // Fill product quota (3 items)
        for ($i = 1; $i <= 3; $i++) {
            Product::create([
                'barcode' => "BC-ST-{$i}",
                'sku' => "SKU-ST-{$i}",
                'title' => "Produk {$i}",
                'category_id' => $category->id,
                'buy_price' => 1000,
                'sell_price' => 2000,
                'stock' => 10,
                'tax_rate' => 0,
            ]);
        }

        // Post 4th product via route as authenticated admin
        $response = $this->actingAs($this->starterAdmin)
            ->post(route('products.store'), [
                'barcode' => 'BC-ST-OVER',
                'sku' => 'SKU-ST-OVER',
                'title' => 'Produk Lebih',
                'category_id' => $category->id,
                'buy_price' => 1000,
                'sell_price' => 2000,
                'stock' => 10,
            ]);

        // Should be redirected back with error flash
        $response->assertSessionHas('error');
        $this->assertEquals(3, Product::count());
    }

    public function test_feature_gate_blocks_starter_but_allows_pro(): void
    {
        // 1. Starter tenant accessing CRM campaigns -> blocked
        TenantContext::set($this->starterTenant);
        $quotaStarter = app(QuotaService::class);
        $this->assertFalse($quotaStarter->hasFeature('crm'));

        // 2. Pro tenant accessing CRM campaigns -> allowed
        TenantContext::set($this->proTenant);
        $quotaPro = app(QuotaService::class);
        $this->assertTrue($quotaPro->hasFeature('crm'));
        $this->assertTrue($quotaPro->hasFeature('multi_warehouse'));
    }

    public function test_feature_middleware_blocks_crm_route_for_starter_tenant(): void
    {
        $response = $this->actingAs($this->starterAdmin)->get(route('crm-campaigns.index'));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('crm', (string) session('error'));
    }

    public function test_feature_middleware_allows_crm_route_for_pro_tenant(): void
    {
        $response = $this->actingAs($this->proAdmin)->get(route('crm-campaigns.index'));

        $response->assertOk();
    }

    public function test_customer_store_route_blocks_when_quota_exceeded(): void
    {
        TenantContext::set($this->starterTenant);

        // Starter test plan allows 5 customers.
        for ($i = 1; $i <= 5; $i++) {
            Customer::create([
                'name' => "Pelanggan {$i}",
                'no_telp' => '08120000000'.$i,
                'address' => 'Jl. Contoh No. '.$i,
            ]);
        }

        $response = $this->actingAs($this->starterAdmin)
            ->post(route('customers.store'), $this->customerPayload('Pelanggan Keenam', '081299999999'));

        $response->assertSessionHas('error');
        $this->assertEquals(5, Customer::count());
    }

    public function test_customer_store_route_allows_creation_under_quota(): void
    {
        TenantContext::set($this->starterTenant);

        $response = $this->actingAs($this->starterAdmin)
            ->post(route('customers.store'), $this->customerPayload('Pelanggan Baru', '081211112222'));

        $this->assertNull(session('error'));
        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, Customer::count());
    }

    private function customerPayload(string $name, string $phone): array
    {
        return [
            'name' => $name,
            'no_telp' => $phone,
            'address' => 'Jl. Baru No. 1',
            'province_id' => '11',
            'regency_id' => '1101',
            'district_id' => '110101',
            'village_id' => '1101011001',
        ];
    }
}
