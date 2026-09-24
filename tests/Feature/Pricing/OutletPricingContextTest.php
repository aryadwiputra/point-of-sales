<?php

namespace Tests\Feature\Pricing;

use App\Http\Controllers\DocumentController;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\PriceList;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use App\Services\TaxService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OutletPricingContextTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Outlet $outlet;

    private Warehouse $warehouse;

    private Category $category;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        $this->cashier = User::where('email', 'cashier@gmail.com')->first();

        $this->outlet = Outlet::create([
            'code' => 'OUT-PB',
            'name' => 'Outlet Phase B',
            'is_active' => true,
            'is_sales_enabled' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'WH-PB',
            'name' => 'Gudang Phase B',
            'status' => 'active',
            'outlet_id' => $this->outlet->id,
        ]);

        $this->category = Category::create([
            'name' => 'Kategori PB',
            'image' => '',
            'description' => '',
        ]);

        $this->product = Product::create([
            'title' => 'Produk PB',
            'barcode' => 'PB-001',
            'sku' => 'SKU-PB-001',
            'image' => '',
            'description' => '',
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 50,
            'category_id' => $this->category->id,
            'tax_rate' => 0,
        ]);
        $this->product->warehouses()->attach($this->warehouse->id, ['stock' => 50]);

        Sanctum::actingAs($this->cashier, ['*']);
    }

    private function openShift(): void
    {
        app(CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );
    }

    private function memberCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Member PB',
            'no_telp' => '081200000001',
            'address' => 'Jl. PB No. 1',
            'is_loyalty_member' => true,
            'loyalty_tier' => 'gold',
            'loyalty_points' => 0,
        ]);
    }

    public function test_outlet_price_list_is_used_as_discount_basis_for_rules(): void
    {
        $this->openShift();

        $priceList = PriceList::create([
            'name' => 'Harga Outlet',
            'slug' => 'harga-outlet-pb',
            'outlet_id' => $this->outlet->id,
            'customer_scope' => 'all',
            'is_active' => true,
            'priority' => 10,
        ]);
        $priceList->items()->create([
            'product_id' => $this->product->id,
            'price' => 8000,
        ]);

        PricingRule::create([
            'name' => 'Diskon 10% Semua',
            'is_active' => true,
            'priority' => 100,
            'target_type' => 'all',
            'customer_scope' => 'all',
            'discount_type' => PricingRule::TYPE_PERCENTAGE,
            'discount_value' => 10,
        ]);

        $customer = $this->memberCustomer();

        $this->postJson('/api/v1/pos/cart', [
            'product_id' => $this->product->id,
            'qty' => 2,
            'customer_id' => $customer->id,
        ]);

        $response = $this->postJson('/api/v1/pos/checkout', [
            'payment_method' => 'cash',
            'cash' => 50000,
            'customer_id' => $customer->id,
        ]);

        // Outlet price 8000 x2 = 16000, minus 10% = 14400.
        // Global price 10000 x2 = 20000 would have produced 18000 before the fix.
        $response->assertCreated()
            ->assertJsonPath('data.grand_total', 14400);

        $this->assertDatabaseHas('transactions', [
            'id' => $response->json('data.id'),
            'grand_total' => 14400,
            'price_list_id' => $priceList->id,
        ]);
    }

    public function test_pos_pricing_badge_uses_outlet_price_list(): void
    {
        $this->openShift();

        $priceList = PriceList::create([
            'name' => 'Harga Outlet Badge',
            'slug' => 'harga-outlet-badge',
            'outlet_id' => $this->outlet->id,
            'customer_scope' => 'all',
            'is_active' => true,
            'priority' => 10,
        ]);
        $priceList->items()->create([
            'product_id' => $this->product->id,
            'price' => 7000,
        ]);

        PricingRule::create([
            'name' => 'Promo Outlet Badge',
            'is_active' => true,
            'priority' => 100,
            'target_type' => 'product',
            'product_id' => $this->product->id,
            'customer_scope' => 'all',
            'discount_type' => PricingRule::TYPE_PERCENTAGE,
            'discount_value' => 10,
        ]);

        $response = $this->get(route('transactions.index'));

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $products = collect($page->toArray()['props']['products']);
            $product = $products->firstWhere('id', $this->product->id);

            $this->assertNotNull($product);
            $this->assertSame(7000, (int) $product['pricing_badge']['base_price']);
        });
    }

    public function test_tax_default_rate_resolves_per_outlet(): void
    {
        Setting::set('tax_default_rate', '11.00');
        Setting::setForOutlet('tax_default_rate', '5.00', $this->outlet);

        $service = app(TaxService::class);

        $this->assertSame(5.0, $service->getDefaultRate($this->outlet));
        $this->assertSame(11.0, $service->getDefaultRate(null));
    }

    public function test_document_store_profile_resolves_per_outlet(): void
    {
        Setting::set('store_name', 'Toko Global');
        Setting::set('store_address', 'Alamat Global');
        Setting::setForOutlet('store_name', 'Toko Outlet PB', $this->outlet);

        $controller = app(DocumentController::class);
        $method = new \ReflectionMethod($controller, 'storeProfile');
        $method->setAccessible(true);

        $outletProfile = $method->invoke($controller, $this->outlet);
        $globalProfile = $method->invoke($controller, null);

        $this->assertSame('Toko Outlet PB', $outletProfile['name']);
        $this->assertSame('Alamat Global', $outletProfile['address']);
        $this->assertSame('Toko Global', $globalProfile['name']);
    }
}
