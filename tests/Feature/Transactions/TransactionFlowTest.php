<?php

namespace Tests\Feature\Transactions;

use App\Models\Cart;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\PaymentSetting;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
        Permission::firstOrCreate([
            'name' => 'cashier-shifts-access',
            'guard_name' => 'web',
        ]);
        Permission::firstOrCreate([
            'name' => 'cashier-shifts-open',
            'guard_name' => 'web',
        ]);
        Permission::firstOrCreate([
            'name' => 'cashier-shifts-close',
            'guard_name' => 'web',
        ]);
    }

    public function test_cashier_can_complete_transaction_and_redirects_to_invoice(): void
    {
        $cashier = $this->createCashier();
        $shift = $this->openShiftFor($cashier);
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
        $this->assertSame($shift->id, $transaction->cashier_shift_id);
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
        $expectedProfit = (($product->sell_price * $quantity) - $discount)
            - ($product->buy_price * $quantity);
        $this->assertSame($expectedProfit, (int) $profit->total);

        $this->assertDatabaseMissing('carts', ['id' => $cart->id]);
        $this->assertSame($product->stock - $quantity, $product->fresh()->stock);
    }

    public function test_cashier_can_view_invoice_page_after_transaction(): void
    {
        $cashier = $this->createCashier();
        $shift = $this->openShiftFor($cashier);
        $customer = Customer::create([
            'name' => 'Jane Customer',
            'no_telp' => 62856789,
            'address' => 'Jl. Inertia No. 2',
        ]);
        $product = $this->createProduct();

        $transaction = Transaction::create([
            'cashier_id' => $cashier->id,
            'cashier_shift_id' => $shift->id,
            'customer_id' => $customer->id,
            'invoice' => 'TRX-'.Str::upper(Str::random(8)),
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

    public function test_transaction_page_serializes_product_and_category_numeric_fields_as_integers(): void
    {
        $cashier = $this->createCashier();
        $shift = $this->openShiftFor($cashier);
        $product = $this->createProduct();

        $response = $this
            ->actingAs($cashier)
            ->get(route('transactions.index'));

        $response
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($product, $shift) {
                $page->component('Dashboard/Transactions/Index');

                $products = $page->toArray()['props']['products'] ?? [];
                $categories = $page->toArray()['props']['categories'] ?? [];

                $serializedProduct = collect($products)->firstWhere('id', $product->id);
                $serializedCategory = collect($categories)->firstWhere('id', $product->category_id);

                $this->assertIsInt($serializedProduct['id']);
                $this->assertIsInt($serializedProduct['category_id']);
                $this->assertIsInt($serializedProduct['sell_price']);
                $this->assertIsInt($serializedProduct['stock']);
                $this->assertIsInt($serializedCategory['id']);
                $this->assertSame($shift->id, $page->toArray()['props']['activeCashierShift']['id']);
            });
    }

    public function test_cashier_can_request_midtrans_payment_link(): void
    {
        $cashier = $this->createCashier();
        $shift = $this->openShiftFor($cashier);
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
        $this->assertSame($shift->id, $transaction->cashier_shift_id);
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
        $user->givePermissionTo([
            'transactions-access',
            'cashier-shifts-access',
            'cashier-shifts-open',
            'cashier-shifts-close',
        ]);

        return $user;
    }

    public function test_cashier_cannot_store_transaction_without_active_shift(): void
    {
        $cashier = $this->createCashier();
        $customer = Customer::create([
            'name' => 'No Shift',
            'no_telp' => 62812345,
            'address' => 'Jl. Tanpa Shift',
        ]);
        $product = $this->createProduct();

        Cart::create([
            'cashier_id' => $cashier->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => $product->sell_price,
        ]);

        $response = $this
            ->from(route('transactions.index'))
            ->actingAs($cashier)
            ->post(route('transactions.store'), [
                'customer_id' => $customer->id,
                'discount' => 0,
                'grand_total' => $product->sell_price,
                'cash' => $product->sell_price,
                'change' => 0,
            ]);

        $response->assertRedirect(route('transactions.index'));
        $response->assertSessionHas('error', 'Shift kasir belum dibuka.');
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_transaction_history_includes_legacy_null_warehouse_in_single_outlet_install(): void
    {
        $cashier = $this->createCashier();
        $warehouse = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
        ]);

        foreach ([
            ['invoice' => 'TRX-WAREHOUSE', 'warehouse_id' => $warehouse->id],
            ['invoice' => 'TRX-LEGACY', 'warehouse_id' => null],
        ] as $attributes) {
            Transaction::create([
                'cashier_id' => $cashier->id,
                ...$attributes,
                'cash' => 10000,
                'change' => 0,
                'discount' => 0,
                'grand_total' => 10000,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
            ]);
        }

        $response = $this->actingAs($cashier)->get(route('transactions.history'));

        $response->assertInertia(function (Assert $page) {
            $invoices = collect($page->toArray()['props']['transactions']['data'])
                ->pluck('invoice');

            $this->assertTrue($invoices->contains('TRX-WAREHOUSE'));
            $this->assertTrue($invoices->contains('TRX-LEGACY'));
        });
    }

    public function test_transaction_history_excludes_legacy_null_warehouse_in_multi_outlet_install(): void
    {
        $outletA = Outlet::create([
            'code' => 'MAL',
            'name' => 'Outlet Malabar',
            'is_active' => true,
            'is_sales_enabled' => true,
        ]);
        $outletB = Outlet::create([
            'code' => 'TKB',
            'name' => 'Outlet Taman Kencana',
            'is_active' => true,
            'is_sales_enabled' => true,
        ]);
        $warehouse = Warehouse::create([
            'outlet_id' => $outletA->id,
            'code' => 'WH-MAL',
            'name' => 'Gudang Malabar',
            'type' => 'branch',
            'is_active' => true,
        ]);
        Warehouse::create([
            'outlet_id' => $outletB->id,
            'code' => 'WH-TKB',
            'name' => 'Gudang Taman Kencana',
            'type' => 'branch',
            'is_active' => true,
        ]);
        $cashier = $this->createCashier();

        foreach ([
            ['invoice' => 'TRX-OUTLET', 'warehouse_id' => $warehouse->id],
            ['invoice' => 'TRX-LEGACY', 'warehouse_id' => null],
        ] as $attributes) {
            Transaction::create([
                'cashier_id' => $cashier->id,
                ...$attributes,
                'cash' => 10000,
                'change' => 0,
                'discount' => 0,
                'grand_total' => 10000,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
            ]);
        }

        $response = $this->actingAs($cashier)->get(route('transactions.history'));

        $response->assertInertia(function (Assert $page) {
            $invoices = collect($page->toArray()['props']['transactions']['data'])
                ->pluck('invoice');

            $this->assertTrue($invoices->contains('TRX-OUTLET'));
            $this->assertFalse($invoices->contains('TRX-LEGACY'));
        });
    }

    protected function openShiftFor(User $cashier)
    {
        return CashierShift::create([
            'user_id' => $cashier->id,
            'opened_by' => $cashier->id,
            'opened_at' => now(),
            'opening_cash' => 100000,
            'expected_cash' => 100000,
            'status' => 'open',
        ]);
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
            'barcode' => 'BRCD-'.Str::upper(Str::random(10)),
            'title' => 'Produk Uji',
            'description' => 'Deskripsi produk uji.',
            'buy_price' => 45000,
            'sell_price' => 60000,
            'stock' => 25,
            'tax_rate' => 0,
        ]);
    }
}
