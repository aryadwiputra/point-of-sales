<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosApiCashValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Warehouse $warehouse;

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
        $category = Category::create([
            'name' => 'Kategori Test',
            'image' => '',
            'description' => '',
        ]);
        $this->product = Product::create([
            'title' => 'Produk Kasir',
            'barcode' => 'CASH-'.uniqid(),
            'sku' => 'SKU-CASH-'.uniqid(),
            'image' => '',
            'description' => '',
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 50,
            'category_id' => $category->id,
            'tax_type' => 'exclusive',
            'tax_rate' => 0,
            'min_stock' => 0,
            'max_stock' => 100,
            'is_composite' => false,
        ]);
        $this->product->warehouses()->attach($this->warehouse->id, ['stock' => 50]);

        Sanctum::actingAs($this->cashier, ['*']);

        app(CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );
    }

    private function addToCart(): void
    {
        $this->postJson('/api/v1/pos/cart', [
            'product_id' => $this->product->id,
            'qty' => 1,
        ])->assertOk();
    }

    private function pivotStock(): int
    {
        return (int) $this->warehouse->products()->where('product_id', $this->product->id)->first()->pivot->stock;
    }

    public function test_cash_zero_is_rejected(): void
    {
        $this->addToCart();

        $this->postJson('/api/v1/pos/checkout', [
            'payment_method' => 'cash',
            'cash' => 0,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('cash');

        $this->assertEquals(0, Transaction::count());
        $this->assertEquals(50, $this->pivotStock());
        $this->assertEquals(1, Cart::where('product_id', $this->product->id)->count());
    }

    public function test_cash_below_total_is_rejected(): void
    {
        $this->addToCart();

        $this->postJson('/api/v1/pos/checkout', [
            'payment_method' => 'cash',
            'cash' => 9999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('cash');

        $this->assertEquals(0, Transaction::count());
        $this->assertEquals(50, $this->pivotStock());
    }

    public function test_cash_equal_total_succeeds(): void
    {
        $this->addToCart();

        $this->postJson('/api/v1/pos/checkout', [
            'payment_method' => 'cash',
            'cash' => 10000,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.change', 0);

        $this->assertEquals(49, $this->pivotStock());
    }

    public function test_cash_above_total_succeeds_with_change(): void
    {
        $this->addToCart();

        $this->postJson('/api/v1/pos/checkout', [
            'payment_method' => 'cash',
            'cash' => 50000,
        ])->assertCreated()
            ->assertJsonPath('data.change', 40000);

        $this->assertEquals(49, $this->pivotStock());
    }
}
