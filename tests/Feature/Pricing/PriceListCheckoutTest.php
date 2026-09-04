<?php

namespace Tests\Feature\Pricing;

use App\Models\Category;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PriceListCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Warehouse $warehouse;

    private Category $category;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        $this->cashier = User::where('email', 'cashier@gmail.com')->first();
        $this->warehouse = Warehouse::create([
            'code' => 'WH-1',
            'name' => 'Gudang Utama',
            'status' => 'active',
        ]);
        $this->category = Category::create([
            'name' => 'Kategori Test',
            'image' => '',
            'description' => '',
        ]);
        $this->product = Product::create([
            'title' => 'Produk Kasir',
            'barcode' => 'POS-001',
            'sku' => 'SKU-POS-001',
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
            'name' => 'Member Uji',
            'no_telp' => '081234567890',
            'address' => 'Jl. Test No. 1',
            'is_loyalty_member' => true,
            'loyalty_tier' => 'gold',
            'loyalty_points' => 0,
        ]);
    }

    private function memberPriceList(int $price): PriceList
    {
        $priceList = PriceList::create([
            'name' => 'Harga Member',
            'slug' => 'harga-member',
            'customer_scope' => 'member',
            'is_active' => true,
            'priority' => 10,
        ]);
        $priceList->items()->create([
            'product_id' => $this->product->id,
            'price' => $price,
        ]);

        return $priceList;
    }

    public function test_member_customer_pays_price_list_price(): void
    {
        $this->openShift();
        $priceList = $this->memberPriceList(8000);
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

        $response->assertCreated()
            ->assertJsonPath('data.grand_total', 16000);

        $this->assertDatabaseHas('transactions', [
            'id' => $response->json('data.id'),
            'customer_id' => $customer->id,
            'price_list_id' => $priceList->id,
            'grand_total' => 16000,
        ]);
    }

    public function test_walk_in_customer_pays_normal_price(): void
    {
        $this->openShift();
        $this->memberPriceList(8000);

        $this->postJson('/api/v1/pos/cart', [
            'product_id' => $this->product->id,
            'qty' => 2,
        ]);

        $response = $this->postJson('/api/v1/pos/checkout', [
            'payment_method' => 'cash',
            'cash' => 50000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.grand_total', 20000);

        $this->assertDatabaseHas('transactions', [
            'id' => $response->json('data.id'),
            'price_list_id' => null,
            'grand_total' => 20000,
        ]);
    }

    public function test_product_without_price_list_item_falls_back_to_sell_price(): void
    {
        $this->openShift();
        $priceList = $this->memberPriceList(8000);
        $otherProduct = Product::create([
            'title' => 'Produk Lain',
            'barcode' => 'POS-002',
            'sku' => 'SKU-POS-002',
            'image' => '',
            'description' => '',
            'buy_price' => 3000,
            'sell_price' => 15000,
            'stock' => 50,
            'category_id' => $this->category->id,
            'tax_rate' => 0,
        ]);
        $otherProduct->warehouses()->attach($this->warehouse->id, ['stock' => 50]);
        $customer = $this->memberCustomer();

        $this->postJson('/api/v1/pos/cart', [
            'product_id' => $otherProduct->id,
            'qty' => 1,
            'customer_id' => $customer->id,
        ]);

        $response = $this->postJson('/api/v1/pos/checkout', [
            'payment_method' => 'cash',
            'cash' => 50000,
            'customer_id' => $customer->id,
        ]);

        // In price list but no item for this product → sell_price 15000
        $response->assertCreated()
            ->assertJsonPath('data.grand_total', 15000);

        $this->assertDatabaseHas('transactions', [
            'id' => $response->json('data.id'),
            'price_list_id' => $priceList->id,
            'grand_total' => 15000,
        ]);
    }

    public function test_registered_scope_matches_any_logged_in_customer(): void
    {
        $this->openShift();
        $priceList = PriceList::create([
            'name' => 'Harga Member Terdaftar',
            'slug' => 'harga-member-terdaftar',
            'customer_scope' => 'registered',
            'is_active' => true,
            'priority' => 5,
        ]);
        $priceList->items()->create([
            'product_id' => $this->product->id,
            'price' => 9000,
        ]);
        $customer = Customer::create([
            'name' => 'Pelanggan Biasa',
            'no_telp' => '081298765432',
            'address' => 'Jl. Test No. 2',
            'is_loyalty_member' => false,
            'loyalty_points' => 0,
        ]);

        $this->postJson('/api/v1/pos/cart', [
            'product_id' => $this->product->id,
            'qty' => 1,
            'customer_id' => $customer->id,
        ]);

        $response = $this->postJson('/api/v1/pos/checkout', [
            'payment_method' => 'cash',
            'cash' => 50000,
            'customer_id' => $customer->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.grand_total', 9000);

        $this->assertDatabaseHas('transactions', [
            'id' => $response->json('data.id'),
            'price_list_id' => $priceList->id,
        ]);
    }

    public function test_inactive_price_list_is_ignored(): void
    {
        $this->openShift();
        $this->memberPriceList(8000)->update(['is_active' => false]);
        $customer = $this->memberCustomer();

        $this->postJson('/api/v1/pos/cart', [
            'product_id' => $this->product->id,
            'qty' => 1,
            'customer_id' => $customer->id,
        ]);

        $response = $this->postJson('/api/v1/pos/checkout', [
            'payment_method' => 'cash',
            'cash' => 50000,
            'customer_id' => $customer->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.grand_total', 10000);

        $this->assertDatabaseHas('transactions', [
            'id' => $response->json('data.id'),
            'price_list_id' => null,
        ]);
    }

    public function test_higher_priority_price_list_wins(): void
    {
        $this->openShift();
        $this->memberPriceList(8000);
        $priorityList = PriceList::create([
            'name' => 'Harga Priority',
            'slug' => 'harga-priority',
            'customer_scope' => 'member',
            'is_active' => true,
            'priority' => 20,
        ]);
        $priorityList->items()->create([
            'product_id' => $this->product->id,
            'price' => 7000,
        ]);
        $customer = $this->memberCustomer();

        $this->postJson('/api/v1/pos/cart', [
            'product_id' => $this->product->id,
            'qty' => 1,
            'customer_id' => $customer->id,
        ]);

        $response = $this->postJson('/api/v1/pos/checkout', [
            'payment_method' => 'cash',
            'cash' => 50000,
            'customer_id' => $customer->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.grand_total', 7000);

        $this->assertDatabaseHas('transactions', [
            'id' => $response->json('data.id'),
            'price_list_id' => $priorityList->id,
        ]);
    }
}
