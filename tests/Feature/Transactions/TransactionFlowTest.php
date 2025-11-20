<?php

namespace Tests\Feature\Transactions;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PaymentSetting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TransactionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate([
            'name' => 'transactions-access',
            'guard_name' => 'web',
        ]);
    }

    public function test_cashier_can_complete_transaction_and_redirects_to_invoice(): void
    {
        $cashier = $this->createCashier();
        $customer = Customer::create([
            'name' => 'John Doe',
            'no_telp' => 62812345,
            'address' => 'Jl. Pengujian No. 1',
        ]);
        $product = $this->createProduct();

        $quantity = 2;
        $cart = Cart::create([
            'cashier_id' => $cashier->id,
            'product_id' => $product->id,
            'qty' => $quantity,
            'price' => $product->sell_price * $quantity,
        ]);

        $discount = 5000;
        $grandTotal = $cart->price - $discount;
        $cashPaid = 150000;

        $response = $this
            ->actingAs($cashier)
            ->post(route('transactions.store'), [
                'customer_id' => $customer->id,
                'discount' => $discount,
                'grand_total' => $grandTotal,
                'cash' => $cashPaid,
                'change' => $cashPaid - $grandTotal,
            ]);

        $transaction = Transaction::with(['details', 'profits'])->latest('id')->first();

        $this->assertNotNull($transaction, 'Transaction record should exist after checkout.');
        $response->assertRedirect(route('transactions.print', $transaction->invoice));
        $this->assertStringStartsWith('TRX-', $transaction->invoice);
        $this->assertSame($cashier->id, $transaction->cashier_id);
        $this->assertSame($customer->id, $transaction->customer_id);
        $this->assertSame($grandTotal, (int) $transaction->grand_total);
        $this->assertSame($discount, (int) $transaction->discount);
        $this->assertSame($cashPaid, (int) $transaction->cash);
        $this->assertSame($cashPaid - $grandTotal, (int) $transaction->change);
        $this->assertSame('cash', $transaction->payment_method);
        $this->assertSame('paid', $transaction->payment_status);
        $this->assertNull($transaction->payment_url);

        $this->assertSame(1, $transaction->details->count());
        $detail = $transaction->details->first();
        $this->assertSame($product->id, $detail->product_id);
        $this->assertSame($quantity, (int) $detail->qty);
        $this->assertSame($cart->price, (int) $detail->price);

        $this->assertSame(1, $transaction->profits->count());
        $profit = $transaction->profits->first();
        $expectedProfit = ($product->sell_price - $product->buy_price) * $quantity;
        $this->assertSame($expectedProfit, (int) $profit->total);

        $this->assertDatabaseMissing('carts', ['id' => $cart->id]);
        $this->assertSame($product->stock - $quantity, $product->fresh()->stock);
    }

    public function test_cashier_can_view_invoice_page_after_transaction(): void
    {
        $cashier = $this->createCashier();
        $customer = Customer::create([
            'name' => 'Jane Customer',
            'no_telp' => 62856789,
            'address' => 'Jl. Inertia No. 2',
        ]);
        $product = $this->createProduct();

        $transaction = Transaction::create([
            'cashier_id' => $cashier->id,
            'customer_id' => $customer->id,
            'invoice' => 'TRX-' . Str::upper(Str::random(8)),
            'cash' => 200000,
            'change' => 50000,
            'discount' => 10000,
            'grand_total' => 150000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $transaction->details()->create([
            'product_id' => $product->id,
            'qty' => 3,
            'price' => $product->sell_price * 3,
        ]);

        $response = $this
            ->actingAs($cashier)
            ->get(route('transactions.print', $transaction->invoice));

        $response
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Dashboard/Transactions/Print')
                    ->where('transaction.invoice', $transaction->invoice)
                    ->where('transaction.grand_total', $transaction->grand_total)
                    ->where('transaction.customer.name', $customer->name)
                    ->where('transaction.cashier.name', $cashier->name)
                    ->where('transaction.details.0.product.title', $product->title)
                    ->where('transaction.details.0.qty', 3)
            );
    }

    public function test_cashier_can_request_midtrans_payment_link(): void
    {
        $cashier = $this->createCashier();
        $customer = Customer::create([
            'name' => 'Tony Midtrans',
            'no_telp' => 62899000,
            'address' => 'Jl. Gateway No. 9',
        ]);
        $product = $this->createProduct();

        PaymentSetting::create([
            'default_gateway' => 'midtrans',
            'midtrans_enabled' => true,
            'midtrans_server_key' => 'server-key',
            'midtrans_client_key' => 'client-key',
        ]);

        Http::fake([
            'https://app.sandbox.midtrans.com/*' => Http::response([
                'order_id' => 'TRX-MIDTRANS',
                'redirect_url' => 'https://pay.midtrans.test/invoice',
                'token' => 'snap-token',
            ], 200),
        ]);

        $cart = Cart::create([
            'cashier_id' => $cashier->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => $product->sell_price,
        ]);

        $response = $this
            ->actingAs($cashier)
            ->post(route('transactions.store'), [
                'customer_id' => $customer->id,
                'discount' => 0,
                'grand_total' => $cart->price,
                'cash' => 0,
                'change' => 0,
                'payment_gateway' => 'midtrans',
            ]);

        $transaction = Transaction::latest('id')->first();

        $this->assertNotNull($transaction);
        $response->assertRedirect(route('transactions.print', $transaction->invoice));
        $this->assertSame('midtrans', $transaction->payment_method);
        $this->assertSame('pending', $transaction->payment_status);
        $this->assertSame('https://pay.midtrans.test/invoice', $transaction->payment_url);
        $this->assertSame('TRX-MIDTRANS', $transaction->payment_reference);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'midtrans.com')
            && $request['transaction_details']['order_id'] === $transaction->invoice);
    }

    protected function createCashier(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('transactions-access');

        return $user;
    }

    protected function createProduct(): Product
    {
        $category = Category::create([
            'name' => 'Sembako',
            'description' => 'Kategori pengujian',
            'image' => 'category.png',
        ]);

        return Product::create([
            'category_id' => $category->id,
            'image' => 'product.png',
            'barcode' => 'BRCD-' . Str::upper(Str::random(10)),
            'title' => 'Produk Uji',
            'description' => 'Deskripsi produk uji.',
            'buy_price' => 45000,
            'sell_price' => 60000,
            'stock' => 25,
        ]);
    }
}
