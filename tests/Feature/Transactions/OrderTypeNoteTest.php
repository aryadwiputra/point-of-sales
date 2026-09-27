<?php

namespace Tests\Feature\Transactions;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OrderTypeNoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['transactions-access', 'cashier-shifts-access'] as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_web_checkout_stores_order_type_and_note(): void
    {
        [$cashier, $product] = $this->prepareCheckout();

        $response = $this
            ->actingAs($cashier)
            ->post(route('transactions.store'), [
                'grand_total' => $product->sell_price,
                'cash' => 100000,
                'change' => 100000 - $product->sell_price,
                'order_type' => 'takeaway',
                'note' => 'Pedas, tanpa bawang',
            ]);

        $transaction = Transaction::latest('id')->first();

        $response->assertRedirect(route('transactions.print', $transaction->invoice));
        $this->assertSame('takeaway', $transaction->order_type);
        $this->assertSame('Pedas, tanpa bawang', $transaction->note);
    }

    public function test_order_type_defaults_to_null_when_omitted(): void
    {
        [$cashier, $product] = $this->prepareCheckout();

        $this
            ->actingAs($cashier)
            ->post(route('transactions.store'), [
                'grand_total' => $product->sell_price,
                'cash' => 100000,
                'change' => 100000 - $product->sell_price,
            ]);

        $transaction = Transaction::latest('id')->first();

        $this->assertNull($transaction->order_type);
        $this->assertNull($transaction->note);
    }

    public function test_invalid_order_type_is_stored_as_null(): void
    {
        [$cashier, $product] = $this->prepareCheckout();

        $this
            ->actingAs($cashier)
            ->post(route('transactions.store'), [
                'grand_total' => $product->sell_price,
                'cash' => 100000,
                'change' => 100000 - $product->sell_price,
                'order_type' => 'teleportation',
            ]);

        $transaction = Transaction::latest('id')->first();

        $this->assertNull($transaction->order_type);
    }

    public function test_api_checkout_stores_order_type_and_note(): void
    {
        [$cashier, $product] = $this->prepareCheckout();
        $token = $cashier->createToken('test', ['*'])->plainTextToken;

        $this
            ->withToken($token)
            ->postJson('/api/v1/pos/checkout', [
                'cash' => 100000,
                'payment_method' => 'cash',
                'order_type' => 'delivery',
                'note' => 'Antar jam 5 sore',
            ])
            ->assertCreated();

        $transaction = Transaction::latest('id')->first();

        $this->assertSame('delivery', $transaction->order_type);
        $this->assertSame('Antar jam 5 sore', $transaction->note);
    }

    private function prepareCheckout(): array
    {
        $cashier = User::factory()->create();
        $cashier->markEmailAsVerified();
        $cashier->givePermissionTo(['transactions-access', 'cashier-shifts-access']);

        $warehouse = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Utama',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        app(CashierShiftService::class)->openShift($cashier, $cashier, 100000, null, $warehouse->id);

        $category = Category::create([
            'name' => 'Sembako',
            'description' => 'Kategori pengujian',
            'image' => '',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'image' => 'product.png',
            'barcode' => 'BRCD-'.Str::upper(Str::random(10)),
            'title' => 'Produk Uji',
            'description' => 'Deskripsi produk uji.',
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 25,
            'tax_rate' => 0,
        ]);

        $warehouse->products()->attach($product->id, ['stock' => 25]);

        Cart::create([
            'cashier_id' => $cashier->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => $product->sell_price,
        ]);

        return [$cashier, $product];
    }
}
