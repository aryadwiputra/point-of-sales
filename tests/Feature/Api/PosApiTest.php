<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosApiTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private Warehouse $warehouse;
    private Category $category;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create();
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
            'tax_type' => 'exclusive',
            'tax_rate' => 0,
            'min_stock' => 0,
            'max_stock' => 100,
            'is_composite' => false,
        ]);
        // Link product to warehouse with stock
        $this->product->warehouses()->attach($this->warehouse->id, ['stock' => 50]);

        Sanctum::actingAs($this->cashier, ['*']);
    }

    private function openShift(): CashierShift
    {
        $service = app(CashierShiftService::class);

        return $service->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );
    }

    public function test_shift_returns_null_when_no_active_shift(): void
    {
        $this->getJson('/api/v1/pos/shift')
            ->assertOk()
            ->assertJsonPath('data.shift', null);
    }

    public function test_open_shift_creates_shift(): void
    {
        $response = $this->postJson('/api/v1/pos/shift/open', [
            'opening_cash' => 100000,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('cashier_shifts', [
            'user_id' => $this->cashier->id,
            'status' => 'open',
        ]);
    }

    public function test_scan_returns_product_with_stock(): void
    {
        $this->openShift();

        $this->postJson('/api/v1/pos/products/scan', ['barcode' => 'POS-001'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Produk Kasir')
            ->assertJsonPath('data.stock', 50);
    }

    public function test_scan_returns_404_for_unknown_barcode(): void
    {
        $this->openShift();

        $this->postJson('/api/v1/pos/products/scan', ['barcode' => 'NOPE'])
            ->assertStatus(404);
    }

    public function test_add_to_cart_and_cart_summary(): void
    {
        $this->openShift();

        $this->postJson('/api/v1/pos/cart', [
            'product_id' => $this->product->id,
            'qty' => 2,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.qty', 2);

        $response = $this->getJson('/api/v1/pos/cart');
        $response->assertOk()
            ->assertJsonPath('data.items.0.product.title', 'Produk Kasir')
            ->assertJsonPath('data.items.0.qty', 2)
            ->assertJsonPath('data.summary.grand_total', 20000);
    }

    public function test_add_to_cart_rejects_insufficient_stock(): void
    {
        $this->openShift();

        $this->postJson('/api/v1/pos/cart', [
            'product_id' => $this->product->id,
            'qty' => 999,
        ])->assertStatus(422);
    }

    public function test_update_cart_qty(): void
    {
        $this->openShift();

        $this->postJson('/api/v1/pos/cart', [
            'product_id' => $this->product->id,
            'qty' => 1,
        ]);

        $cart = Cart::where('cashier_id', $this->cashier->id)->first();

        $this->putJson("/api/v1/pos/cart/{$cart->id}", ['qty' => 5])
            ->assertOk()
            ->assertJsonPath('data.qty', 5)
            ->assertJsonPath('data.price', 50000);
    }

    public function test_hold_resume_flow(): void
    {
        $this->openShift();

        $this->postJson('/api/v1/pos/cart', [
            'product_id' => $this->product->id,
            'qty' => 1,
        ]);

        $hold = $this->postJson('/api/v1/pos/hold', ['label' => 'Customer A']);
        $hold->assertOk()
            ->assertJsonPath('success', true);

        $holdId = $hold->json('data.hold_id');

        // Cart should be empty now
        $this->getJson('/api/v1/pos/cart')
            ->assertJsonCount(0, 'data.items');

        // Held carts listed
        $this->getJson('/api/v1/pos/holds')
            ->assertOk()
            ->assertJsonPath('data.holds.0.hold_id', $holdId);

        // Resume
        $this->postJson("/api/v1/pos/holds/{$holdId}/resume")
            ->assertOk()
            ->assertJsonPath('success', true);

        // Cart should have item again
        $this->getJson('/api/v1/pos/cart')
            ->assertJsonCount(1, 'data.items');
    }

    public function test_checkout_cash_transaction(): void
    {
        $this->openShift();

        $this->postJson('/api/v1/pos/cart', [
            'product_id' => $this->product->id,
            'qty' => 2,
        ]);

        $response = $this->postJson('/api/v1/pos/checkout', [
            'payment_method' => 'cash',
            'cash' => 50000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_method', 'cash')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.grand_total', 20000)
            ->assertJsonPath('data.change', 30000);

        // Stock decremented
        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'stock' => 48,
        ]);

        // Cart cleared
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_checkout_pay_later_creates_receivable(): void
    {
        $this->openShift();

        $customer = Customer::create([
            'name' => 'Budi',
            'phone' => '08123456789',
            'no_telp' => '08123456789',
            'address' => '',
        ]);

        $this->postJson('/api/v1/pos/cart', [
            'product_id' => $this->product->id,
            'qty' => 1,
        ]);

        $response = $this->postJson('/api/v1/pos/checkout', [
            'payment_method' => 'pay_later',
            'customer_id' => $customer->id,
            'due_date' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment_method', 'pay_later')
            ->assertJsonPath('data.payment_status', 'unpaid');

        $this->assertDatabaseHas('receivables', [
            'customer_id' => $customer->id,
            'status' => 'unpaid',
        ]);
    }

    public function test_checkout_requires_active_shift(): void
    {
        // No shift opened
        $this->postJson('/api/v1/pos/cart', [
            'product_id' => $this->product->id,
            'qty' => 1,
        ])->assertStatus(422);
    }

    public function test_checkout_empty_cart_rejected(): void
    {
        $this->openShift();

        $this->postJson('/api/v1/pos/checkout', [
            'payment_method' => 'cash',
            'cash' => 10000,
        ])->assertStatus(422);
    }

    public function test_transaction_history_and_detail(): void
    {
        $this->openShift();

        $this->postJson('/api/v1/pos/cart', [
            'product_id' => $this->product->id,
            'qty' => 1,
        ]);

        $this->postJson('/api/v1/pos/checkout', [
            'payment_method' => 'cash',
            'cash' => 10000,
        ]);

        $this->getJson('/api/v1/pos/transactions')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.payment_method', 'cash');

        $invoice = \App\Models\Transaction::first()->invoice;

        $transaction = \App\Models\Transaction::first();

        $this->getJson("/api/v1/pos/transactions/{$transaction->id}")
            ->assertOk()
            ->assertJsonPath('data.invoice', $invoice);
    }
}
