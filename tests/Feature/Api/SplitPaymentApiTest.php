<?php

namespace Tests\Feature\Api;

use App\Models\BankAccount;
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

class SplitPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_checkout_returns_tenders_and_parent_summary(): void
    {
        [$cashier, $product, $bankAccount] = $this->prepareCheckout();
        Sanctum::actingAs($cashier, ['*']);

        $response = $this->postJson('/api/v1/pos/checkout', [
            'tenders' => [
                ['method' => 'cash', 'amount' => 5000, 'cash_received' => 6000],
                ['method' => 'bank_transfer', 'amount' => 5000, 'bank_account_id' => $bankAccount->id],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment_method', 'split')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonCount(2, 'data.tenders');

        $transaction = Transaction::latest('id')->first();
        $this->assertSame(6000, $transaction->cash);
        $this->assertSame(1000, $transaction->change);
        $this->assertSame(9, (int) $product->fresh()->stock);
    }

    private function prepareCheckout(): array
    {
        $cashier = User::factory()->create();
        $warehouse = Warehouse::create([
            'code' => 'SPLIT-API',
            'name' => 'Gudang API',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $category = Category::create(['name' => 'API Test', 'image' => '', 'description' => '']);
        $product = Product::create([
            'title' => 'Produk API Split', 'barcode' => 'API-SPLIT', 'sku' => 'API-SPLIT',
            'image' => '', 'description' => '', 'buy_price' => 5000, 'sell_price' => 10000,
            'stock' => 10, 'category_id' => $category->id, 'tax_rate' => 0,
        ]);
        $warehouse->products()->attach($product->id, ['stock' => 10]);
        app(CashierShiftService::class)->openShift($cashier, $cashier, 0, null, $warehouse->id);
        Cart::create(['cashier_id' => $cashier->id, 'product_id' => $product->id, 'qty' => 1, 'price' => 10000]);

        $bankAccount = BankAccount::create([
            'bank_name' => 'BCA', 'account_number' => '9876543210', 'account_name' => 'API Test',
            'is_active' => true, 'sort_order' => 0,
        ]);

        return [$cashier, $product, $bankAccount];
    }
}
