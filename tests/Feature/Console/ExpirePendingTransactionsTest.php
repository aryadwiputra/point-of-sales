<?php

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\PaymentSetting;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMutation;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\TransactionDetailBatchAllocation;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpirePendingTransactionsTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Warehouse $warehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
        ]);

        $this->cashier = User::where('email', 'cashier@gmail.com')->first();
        $this->cashier->markEmailAsVerified();

        $this->warehouse = Warehouse::create([
            'name' => 'PUSAT',
            'code' => 'PUSAT',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
            'status' => 'active',
        ]);

        $category = Category::create(['name' => 'Test Category']);

        $this->product = Product::create([
            'image' => 'test.png',
            'barcode' => '1234567890',
            'sku' => 'EXPIRE-1',
            'title' => 'Produk Expire',
            'description' => 'test',
            'category_id' => $category->id,
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 0,
            'tax_rate' => 0,
            'tax_type' => 'exclusive',
        ]);

        $this->warehouse->products()->attach($this->product->id, ['stock' => 0]);
    }

    private function makePendingTransaction(string $invoice, $createdAt, int $conversionFactor = 1): Transaction
    {
        $transaction = new Transaction([
            'cashier_id' => $this->cashier->id,
            'warehouse_id' => $this->warehouse->id,
            'invoice' => $invoice,
            'cash' => 0,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 20000,
            'payment_method' => 'midtrans',
            'payment_status' => 'pending',
        ]);
        $transaction->created_at = $createdAt;
        $transaction->save();

        $detail = TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
            'qty' => 2,
            'conversion_factor' => $conversionFactor,
            'base_unit_price' => 10000,
            'unit_price' => 10000,
            'price' => 10000,
        ]);

        $batch = ProductBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BATCH-'.$invoice,
            'received_at' => now(),
            'stock' => 0,
        ]);

        TransactionDetailBatchAllocation::create([
            'transaction_detail_id' => $detail->id,
            'product_batch_id' => $batch->id,
            'qty' => 2 * $conversionFactor,
        ]);

        return $transaction;
    }

    public function test_expires_old_pending_transaction_and_restocks(): void
    {
        $transaction = $this->makePendingTransaction('INV-EXPIRE-1', now()->subHours(30));

        \Artisan::call('transactions:expire');
        $transaction->refresh();

        $this->assertEquals('failed', $transaction->payment_status);

        // pivot + global + batch restocked
        $this->assertDatabaseHas('product_warehouse', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'stock' => 2,
        ]);
        $this->assertEquals(2, $this->product->fresh()->stock);
        $this->assertEquals(2, $transaction->details->first()->batchAllocations->first()->productBatch->stock);

        $this->assertDatabaseHas(StockMutation::class, [
            'reference_type' => 'transaction_expire',
            'reference_id' => $transaction->id,
            'mutation_type' => 'in',
            'qty' => 2,
        ]);
    }

    public function test_expires_multi_unit_transaction_and_restocks_base_units(): void
    {
        $transaction = $this->makePendingTransaction('INV-EXPIRE-MU', now()->subHours(30), conversionFactor: 12);

        \Artisan::call('transactions:expire');
        $transaction->refresh();

        $this->assertEquals('failed', $transaction->payment_status);

        // qty 2 x factor 12 = 24 base units restored to pivot, global, and batch
        $this->assertDatabaseHas('product_warehouse', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'stock' => 24,
        ]);
        $this->assertEquals(24, $this->product->fresh()->stock);
        $this->assertEquals(24, $transaction->details->first()->batchAllocations->first()->productBatch->stock);

        $this->assertDatabaseHas(StockMutation::class, [
            'reference_type' => 'transaction_expire',
            'reference_id' => $transaction->id,
            'mutation_type' => 'in',
            'qty' => 24,
        ]);
    }

    public function test_fresh_pending_transaction_is_untouched(): void
    {
        $transaction = $this->makePendingTransaction('INV-FRESH-1', now()->subHours(2));

        $this->artisan('transactions:expire')->assertSuccessful();

        $transaction->refresh();

        $this->assertEquals('pending', $transaction->payment_status);
        $this->assertDatabaseHas('product_warehouse', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'stock' => 0,
        ]);
    }

    public function test_pending_approval_transaction_is_untouched(): void
    {
        $transaction = $this->makePendingTransaction('INV-APPROVAL-1', now()->subHours(30));
        $transaction->update(['payment_status' => 'pending_approval']);

        $this->artisan('transactions:expire')->assertSuccessful();

        $transaction->refresh();

        $this->assertEquals('pending_approval', $transaction->payment_status);
    }

    public function test_dry_run_does_not_mutate(): void
    {
        $transaction = $this->makePendingTransaction('INV-DRY-1', now()->subHours(30));

        $this->artisan('transactions:expire', ['--dry-run' => true])->assertSuccessful();

        $transaction->refresh();

        $this->assertEquals('pending', $transaction->payment_status);
        $this->assertDatabaseHas('product_warehouse', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'stock' => 0,
        ]);
    }

    public function test_webhook_ignores_terminal_transaction(): void
    {
        $transaction = $this->makePendingTransaction('INV-TERM-1', now());
        $transaction->update(['payment_status' => 'paid']);

        $paymentSetting = PaymentSetting::create([
            'midtrans_enabled' => true,
            'midtrans_server_key' => 'test-server-key',
        ]);

        $grossAmount = '20000.00';
        $orderId = 'INV-TERM-1';
        $statusCode = '200';
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.'test-server-key');

        $response = $this->postJson('/api/webhooks/midtrans', [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'transaction_id' => 'gateway-ref-1',
        ]);

        $response->assertOk();

        // paid_at must not have been set / status untouched by the late webhook
        $transaction->refresh();
        $this->assertEquals('paid', $transaction->payment_status);
        $this->assertNull($transaction->payment_reference);
    }

    public function test_webhook_ignores_failed_transaction(): void
    {
        $transaction = $this->makePendingTransaction('INV-TERM-2', now());
        $transaction->update(['payment_status' => 'failed']);

        PaymentSetting::create([
            'midtrans_enabled' => true,
            'midtrans_server_key' => 'test-server-key',
        ]);

        $grossAmount = '20000.00';
        $orderId = 'INV-TERM-2';
        $statusCode = '200';
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.'test-server-key');

        $response = $this->postJson('/api/webhooks/midtrans', [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'transaction_id' => 'gateway-ref-2',
        ]);

        $response->assertOk();

        $transaction->refresh();
        $this->assertEquals('failed', $transaction->payment_status);
    }
}
