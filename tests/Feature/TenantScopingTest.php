<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveTenant;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TenantScopingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->plan = Plan::where('slug', 'pro')->first();

        $this->tenantA = Tenant::create([
            'name' => 'Toko Alfa',
            'subdomain' => 'tokoalfa',
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Toko Beta',
            'subdomain' => 'tokobeta',
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);

        TenantContext::reset();
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_models_automatically_scoped_and_isolated_by_tenant(): void
    {
        // 1. Context Tenant A
        TenantContext::set($this->tenantA);

        $catA = Category::create([
            'name' => 'Makanan Ringan Alfa',
        ]);
        $prodA = Product::create([
            'barcode' => 'BAR-A-001',
            'sku' => 'SKU-A-001',
            'title' => 'Keripik Tempe Alfa',
            'category_id' => $catA->id,
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 50,
            'tax_rate' => 0,
        ]);

        $this->assertEquals($this->tenantA->id, $catA->tenant_id);
        $this->assertEquals($this->tenantA->id, $prodA->tenant_id);
        $this->assertEquals(1, Product::count());

        // 2. Switch Context to Tenant B
        TenantContext::set($this->tenantB);

        $this->assertEquals(0, Product::count());
        $this->assertNull(Product::find($prodA->id));

        $catB = Category::create([
            'name' => 'Minuman Beta',
        ]);
        $prodB = Product::create([
            'barcode' => 'BAR-B-001',
            'sku' => 'SKU-B-001',
            'title' => 'Kopi Susu Beta',
            'category_id' => $catB->id,
            'buy_price' => 8000,
            'sell_price' => 18000,
            'stock' => 30,
            'tax_rate' => 0,
        ]);

        $this->assertEquals($this->tenantB->id, $prodB->tenant_id);
        $this->assertEquals(1, Product::count());
        $this->assertEquals($prodB->id, Product::first()->id);
        $this->assertNull(Product::find($prodA->id));

        // 3. Bypass Scope returns all products
        $allProducts = TenantContext::withoutScope(fn () => Product::all());
        $this->assertCount(2, $allProducts);
    }

    public function test_customer_isolation_between_tenants(): void
    {
        TenantContext::set($this->tenantA);
        $custA = Customer::create([
            'name' => 'Budi Alfa',
            'no_telp' => '081234567890',
            'address' => 'Jl. Merdeka No. 1, Jakarta',
        ]);

        TenantContext::set($this->tenantB);
        $custB = Customer::create([
            'name' => 'Siti Beta',
            'no_telp' => '089876543210',
            'address' => 'Jl. Sudirman No. 2, Bandung',
        ]);

        $this->assertEquals(1, Customer::count());
        $this->assertEquals('Siti Beta', Customer::first()->name);
        $this->assertNull(Customer::where('name', 'Budi Alfa')->first());

        TenantContext::set($this->tenantA);
        $this->assertEquals(1, Customer::count());
        $this->assertEquals('Budi Alfa', Customer::first()->name);
    }

    public function test_resolve_tenant_middleware_by_subdomain(): void
    {
        $request = Request::create('http://tokobeta.bpos.test/dashboard');
        $middleware = new ResolveTenant();

        $middleware->handle($request, function ($req) {
            $currentTenant = TenantContext::get();
            $this->assertNotNull($currentTenant);
            $this->assertEquals('tokobeta', $currentTenant->subdomain);
            $this->assertEquals($this->tenantB->id, $currentTenant->id);
            return response('OK');
        });
    }

    public function test_resolve_tenant_middleware_by_header(): void
    {
        $request = Request::create('http://api.bpos.test/api/v1/products', 'GET');
        $request->headers->set('X-Tenant-ID', (string) $this->tenantA->id);
        $middleware = new ResolveTenant();

        $middleware->handle($request, function ($req) {
            $currentTenant = TenantContext::get();
            $this->assertNotNull($currentTenant);
            $this->assertEquals($this->tenantA->id, $currentTenant->id);
            return response('OK');
        });
    }

    public function test_resolve_tenant_middleware_by_authenticated_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Kasir Toko B',
            'email' => 'kasir@tokobeta.com',
            'tenant_id' => $this->tenantB->id,
        ]);

        $request = Request::create('http://localhost/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new ResolveTenant();
        $middleware->handle($request, function ($req) use ($user) {
            $currentTenant = TenantContext::get();
            $this->assertNotNull($currentTenant);
            $this->assertEquals($this->tenantB->id, $currentTenant->id);
            return response('OK');
        });
    }
}
