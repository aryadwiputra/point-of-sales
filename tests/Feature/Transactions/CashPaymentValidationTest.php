<?php

namespace Tests\Feature\Transactions;

use App\Models\Cart;
use App\Models\Category;
use App\Models\PaymentSetting;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CashPaymentValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        $cashier = User::where('email', 'cashier@gmail.com')->first();
        $cashier->markEmailAsVerified();
        $this->actingAs($cashier);

        $category = Category::create([
            'name' => 'Kategori Test',
            'image' => 'categories/test.jpg',
            'description' => 'Kategori untuk test',
        ]);

        $warehouse = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->warehouse = $warehouse;

        $this->product = Product::create([
            'title' => 'Produk Test',
            'sku' => 'SKU-CASH-'.uniqid(),
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 100,
            'image' => 'products/test.jpg',
            'barcode' => 'BC-CASH-'.uniqid(),
            'description' => 'Deskripsi produk test',
            'tax_rate' => 0,
            'category_id' => $category->id,
        ]);
        $warehouse->products()->attach($this->product->id, ['stock' => 100]);

        $this->cashier = $cashier;

        app(CashierShiftService::class)->openShift($cashier, $cashier, 0, null, $warehouse->id);

        $this->post(route('transactions.addToCart'), [
            'product_id' => $this->product->id,
            'sell_price' => 10000,
            'qty' => 1,
        ])->assertSessionHasNoErrors();
    }

    private function stock(): int
    {
        return (int) $this->warehouse->products()->where('product_id', $this->product->id)->first()->pivot->stock;
    }

    public function test_cash_zero_is_rejected_without_side_effects(): void
    {
        $this->post(route('transactions.store'), [
            'payment_method' => 'cash',
            'cash' => 0,
        ])->assertSessionHasErrors('cash');

        $this->assertEquals(0, Transaction::count());
        $this->assertEquals(100, $this->stock());
        $this->assertEquals(1, Cart::where('product_id', $this->product->id)->count());
    }

    public function test_cash_below_total_is_rejected(): void
    {
        $this->post(route('transactions.store'), [
            'payment_method' => 'cash',
            'cash' => 9999,
        ])->assertSessionHasErrors('cash');

        $this->assertEquals(0, Transaction::count());
        $this->assertEquals(100, $this->stock());
    }

    public function test_cash_equal_total_succeeds(): void
    {
        $response = $this->post(route('transactions.store'), [
            'payment_method' => 'cash',
            'cash' => 10000,
        ]);

        $response->assertSessionHasNoErrors();

        $transaction = Transaction::latest('id')->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('cash', $transaction->payment_method);
        $this->assertEquals('paid', $transaction->payment_status);
        $this->assertEquals(10000, (int) $transaction->cash);
        $this->assertEquals(0, (int) $transaction->change);
        $this->assertEquals(99, $this->stock());
    }

    public function test_cash_above_total_succeeds_with_change(): void
    {
        $this->post(route('transactions.store'), [
            'payment_method' => 'cash',
            'cash' => 50000,
        ])->assertSessionHasNoErrors();

        $transaction = Transaction::latest('id')->first();
        $this->assertEquals(40000, (int) $transaction->change);
        $this->assertEquals(99, $this->stock());
    }

    public function test_gateway_payment_with_zero_cash_still_succeeds(): void
    {
        PaymentSetting::create([
            'default_gateway' => 'xendit',
            'xendit_enabled' => true,
            'xendit_secret_key' => 'secret-key',
            'xendit_public_key' => 'public-key',
        ]);

        Http::fake([
            'https://api.xendit.co/*' => Http::response([
                'id' => 'xendit-inv-123',
                'invoice_url' => 'https://checkout.xendit.co/web/123',
            ], 200),
        ]);

        $this->post(route('transactions.store'), [
            'payment_gateway' => 'xendit',
            'cash' => 0,
        ])->assertSessionHasNoErrors();

        $transaction = Transaction::latest('id')->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('xendit', $transaction->payment_method);
        $this->assertEquals('pending', $transaction->payment_status);
        $this->assertEquals('https://checkout.xendit.co/web/123', $transaction->payment_url);
        $this->assertEquals(99, $this->stock());
    }
}
