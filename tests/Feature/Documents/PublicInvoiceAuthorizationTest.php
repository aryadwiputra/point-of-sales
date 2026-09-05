<?php

namespace Tests\Feature\Documents;

use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicInvoiceAuthorizationTest extends TestCase
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

        $product = Product::create([
            'title' => 'Produk Test',
            'sku' => 'SKU-INV-'.uniqid(),
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 100,
            'image' => 'products/test.jpg',
            'barcode' => 'BC-INV-'.uniqid(),
            'description' => 'Deskripsi produk test',
            'tax_rate' => 0,
            'category_id' => $category->id,
        ]);
        $warehouse->products()->attach($product->id, ['stock' => 100]);

        app(CashierShiftService::class)->openShift($cashier, $cashier, 0, null, $warehouse->id);

        $this->post(route('transactions.addToCart'), [
            'product_id' => $product->id,
            'sell_price' => 10000,
            'qty' => 1,
        ]);

        $this->post(route('transactions.store'), [
            'payment_method' => 'cash',
            'cash' => 10000,
        ])->assertSessionHasNoErrors();

        $this->transaction = Transaction::latest('id')->first();
        $this->assertNotNull($this->transaction->access_token);
    }

    public function test_public_invoice_requires_valid_access_token(): void
    {
        $this->get(route('transactions.public', [
            'invoice' => $this->transaction->invoice,
            'token' => $this->transaction->access_token,
        ]))->assertOk();
    }

    public function test_public_invoice_rejects_missing_token(): void
    {
        $this->get(route('transactions.public', $this->transaction->invoice))
            ->assertNotFound();
    }

    public function test_public_invoice_rejects_wrong_token(): void
    {
        $this->get(route('transactions.public', [
            'invoice' => $this->transaction->invoice,
            'token' => 'wrong-token',
        ]))->assertNotFound();
    }

    public function test_public_invoice_rejects_unknown_invoice(): void
    {
        $this->get(route('transactions.public', [
            'invoice' => 'INV-NOT-EXIST',
            'token' => $this->transaction->access_token,
        ]))->assertNotFound();
    }
}
