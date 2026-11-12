<?php

namespace Tests\Feature\Transactions;

use App\Console\Commands\DispatchBihajuPendingCommand;
use App\Models\BihajuBinding;
use App\Models\BihajuTransaction;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Bihaju\BihajuService;
use App\Services\CashierShiftService;
use App\Services\CheckoutService;
use App\Support\Checkout\CheckoutContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BihajuIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected const PARTNER_KEY = 'pk_test_partner_123';

    protected const MERCHANT_KEY = 'mch_test_merchant_456';

    protected User $cashier;

    protected Outlet $outlet;

    protected Warehouse $warehouse;

    protected Product $product;

    protected BihajuBinding $binding;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        config([
            'bihaju.partner_key' => self::PARTNER_KEY,
            'bihaju.base_url' => 'https://bihaju.test',
            // Nonaktifkan emitter Finance agar tes ini hanya mengukur trafik Bihaju
            // (FINANCE_URL di .env lokal tidak boleh mempengaruhi hasil tes).
            'finance.url' => null,
        ]);

        $this->cashier = User::where('email', 'cashier@gmail.com')->first();
        $this->cashier->markEmailAsVerified();

        $this->outlet = Outlet::create([
            'code' => 'OUT-BHJ',
            'name' => 'Outlet Bihaju',
            'is_active' => true,
            'is_sales_enabled' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'GUD-BHJ',
            'name' => 'Gudang Bihaju',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
            'outlet_id' => $this->outlet->id,
        ]);

        $category = Category::create([
            'name' => 'Kategori Bihaju',
            'image' => 'categories/bhj.jpg',
            'description' => 'Kategori test integrasi Bihaju',
        ]);

        $this->product = Product::create([
            'title' => 'Produk Bihaju',
            'sku' => 'SKU-BHJ-'.uniqid(),
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 100,
            'image' => 'products/bhj.jpg',
            'barcode' => 'BC-BHJ-'.uniqid(),
            'description' => 'Produk untuk test integrasi Bihaju',
            'tax_rate' => 0,
            'category_id' => $category->id,
        ]);
        $this->warehouse->products()->attach($this->product->id, ['stock' => 100]);

        app(CashierShiftService::class)->openShift(
            $this->cashier,
            $this->cashier,
            0,
            null,
            $this->warehouse->id,
        );

        $this->binding = BihajuBinding::create([
            'outlet_id' => $this->outlet->id,
            'external_ref' => 'bpos-outlet-'.$this->outlet->id,
            'status' => BihajuBinding::STATUS_ACTIVE,
            'merchant_api_key' => self::MERCHANT_KEY,
            'merchant_id' => 12,
            'merchant_name' => 'Toko Bihaju',
            'bound_at' => now(),
        ]);

        $this->actingAs($this->cashier);
    }

    private function context(int $cash = 10000, ?string $memberCode = null): CheckoutContext
    {
        $this->post(route('transactions.addToCart'), [
            'product_id' => $this->product->id,
            'sell_price' => 10000,
            'qty' => 1,
        ]);

        return new CheckoutContext(
            userId: $this->cashier->id,
            customer: null,
            voucher: null,
            manualDiscount: 0,
            shippingCost: 0,
            requestedRedeemPoints: 0,
            isPayLater: false,
            dueDate: null,
            orderType: null,
            note: null,
            customerNpwp: null,
            isCashPayment: true,
            cashAmount: $cash,
            paymentGateway: null,
            useTenders: false,
            tenderInput: [],
            outlet: null,
            bankAccountId: null,
            bihajuMemberCode: $memberCode,
        );
    }

    // ------------------------------------------------------------------
    // Webhook
    // ------------------------------------------------------------------

    private function sendWebhook(array $payload, ?string $signature, string $event): TestResponse
    {
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return $this->call(
            'POST',
            '/api/bihaju/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_EVENT' => $event,
                'HTTP_X_SIGNATURE' => (string) $signature,
            ],
            $raw
        );
    }

    public function test_webhook_rejects_missing_signature(): void
    {
        $pending = BihajuBinding::create([
            'outlet_id' => $this->outlet->id,
            'external_ref' => 'bpos-pending-nosig',
            'status' => BihajuBinding::STATUS_PENDING,
        ]);

        $payload = ['event' => 'binding.approved', 'external_ref' => $pending->external_ref];

        $this->sendWebhook($payload, null, 'binding.approved')->assertStatus(401);

        $this->assertSame(BihajuBinding::STATUS_PENDING, $pending->fresh()->status);
        $this->assertNull($pending->fresh()->merchant_api_key);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = ['event' => 'binding.approved', 'external_ref' => $this->binding->external_ref];

        $this->sendWebhook($payload, 'signature-salah', 'binding.approved')->assertStatus(401);
    }

    public function test_webhook_binding_approved_stores_encrypted_merchant_key(): void
    {
        $binding = BihajuBinding::create([
            'outlet_id' => $this->outlet->id,
            'external_ref' => 'bpos-pending-1',
            'status' => BihajuBinding::STATUS_PENDING,
        ]);

        $payload = [
            'event' => 'binding.approved',
            'external_ref' => $binding->external_ref,
            'merchant_api_key' => self::MERCHANT_KEY,
            'merchant' => ['id' => 12, 'name' => 'Toko Berkah', 'city' => 'Bandung', 'status' => 'approved'],
            'status' => 'active',
            'occurred_at' => now()->toIso8601String(),
        ];

        $signature = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES), self::PARTNER_KEY);

        $this->sendWebhook($payload, $signature, 'binding.approved')->assertStatus(200);

        $binding->refresh();
        $this->assertSame(BihajuBinding::STATUS_ACTIVE, $binding->status);
        $this->assertSame(self::MERCHANT_KEY, $binding->merchant_api_key);
        $this->assertSame('Toko Berkah', $binding->merchant_name);
        $this->assertNotNull($binding->bound_at);

        // Kunci tidak boleh bocor ke snapshot callback maupun ke client.
        $this->assertArrayNotHasKey('merchant_api_key', $binding->last_callback ?? []);
        $this->assertArrayNotHasKey('merchant_api_key', $binding->toClientArray());
        $this->assertTrue($binding->toClientArray()['has_api_key']);
    }

    public function test_webhook_binding_revoked_clears_merchant_key(): void
    {
        $payload = [
            'event' => 'binding.revoked',
            'external_ref' => $this->binding->external_ref,
            'merchant_api_key' => self::MERCHANT_KEY,
            'status' => 'revoked',
        ];

        $signature = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES), self::PARTNER_KEY);

        $this->sendWebhook($payload, $signature, 'binding.revoked')->assertStatus(200);

        $binding = $this->binding->fresh();
        $this->assertSame(BihajuBinding::STATUS_REVOKED, $binding->status);
        $this->assertNull($binding->merchant_api_key);
        $this->assertNotNull($binding->revoked_at);
    }

    public function test_webhook_unknown_external_ref_is_rejected(): void
    {
        $payload = [
            'event' => 'binding.approved',
            'external_ref' => 'tidak-dikenal',
            'merchant_api_key' => self::MERCHANT_KEY,
        ];

        $signature = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES), self::PARTNER_KEY);

        $this->sendWebhook($payload, $signature, 'binding.approved')->assertStatus(404);
    }

    public function test_webhook_unknown_event_is_ignored(): void
    {
        $payload = ['event' => 'something.else', 'external_ref' => $this->binding->external_ref];

        $signature = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES), self::PARTNER_KEY);

        $this->sendWebhook($payload, $signature, 'something.else')
            ->assertStatus(200)
            ->assertJsonPath('ignored', true);
    }

    // ------------------------------------------------------------------
    // Checkout → Bihaju
    // ------------------------------------------------------------------

    public function test_paid_transaction_from_bound_outlet_is_sent_to_bihaju(): void
    {
        Http::fake([
            'bihaju.test/*' => Http::response([
                'transaction_code' => 'PTR-20261103-XY78Z9',
                'external_ref' => 'x',
                'amount' => 10000,
                'merchant_commission' => 500,
                'member_cashback' => 375,
                'platform_fee' => 125,
                'status' => 'success',
                'merchant_balance_after' => 2495000,
            ], 201),
        ]);

        $result = app(CheckoutService::class)->execute($this->context(memberCode: 'BHJB88'));
        $transaction = $result->transaction;

        $record = BihajuTransaction::withoutGlobalScopes()
            ->where('transaction_id', $transaction->id)
            ->first();

        $this->assertNotNull($record, 'Record pengiriman Bihaju harus dibuat.');
        $this->assertSame(BihajuTransaction::STATUS_SUCCEEDED, $record->status);
        $this->assertSame(10000, $record->amount);
        $this->assertSame('BHJB88', $record->member_code);
        $this->assertSame('PTR-20261103-XY78Z9', $record->bihaju_transaction_code);
        $this->assertSame(500, $record->breakdown['merchant_commission']);
        $this->assertSame(2495000, $record->balance_after);

        // Idempotency key stabil per transaksi BPOS.
        $this->assertSame(
            sprintf('BPOS-%d-%d', (int) $transaction->tenant_id, $transaction->id),
            $record->external_ref
        );

        Http::assertSent(function ($request) {
            return $request->url() === 'https://bihaju.test/api/partner/transactions'
                && $request->hasHeader('X-Partner-Key', self::PARTNER_KEY)
                && $request->hasHeader('X-Merchant-Key', self::MERCHANT_KEY)
                && $request['external_ref'] !== null
                && (int) $request['amount'] === 10000
                && $request['member_code'] === 'BHJB88';
        });
    }

    public function test_bihaju_failure_never_fails_the_sale(): void
    {
        Http::fake([
            'bihaju.test/*' => Http::response([
                'message' => 'Saldo merchant tidak cukup untuk komisi transaksi ini.',
                'code' => 'insufficient_merchant_balance',
            ], 422),
        ]);

        $result = app(CheckoutService::class)->execute($this->context());

        // Penjualan tetap berhasil dan lunas.
        $this->assertSame('paid', $result->transaction->payment_status);
        $this->assertSame(1, Transaction::count());

        $record = BihajuTransaction::withoutGlobalScopes()
            ->where('transaction_id', $result->transaction->id)
            ->firstOrFail();

        $this->assertSame(BihajuTransaction::STATUS_FAILED, $record->status);
        $this->assertStringContainsString('insufficient_merchant_balance', $record->last_error);
    }

    public function test_network_error_keeps_record_pending_for_retry(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timeout');
        });

        $result = app(CheckoutService::class)->execute($this->context());

        $this->assertSame('paid', $result->transaction->payment_status);

        $record = BihajuTransaction::withoutGlobalScopes()
            ->where('transaction_id', $result->transaction->id)
            ->firstOrFail();

        $this->assertSame(BihajuTransaction::STATUS_PENDING, $record->status);
        $this->assertNotNull($record->last_error);
    }

    public function test_unbound_outlet_is_not_sent(): void
    {
        Http::fake();
        $this->binding->update(['status' => BihajuBinding::STATUS_REVOKED, 'merchant_api_key' => null]);

        $result = app(CheckoutService::class)->execute($this->context());

        $this->assertSame(0, BihajuTransaction::withoutGlobalScopes()->count());
        $this->assertSame('paid', $result->transaction->payment_status);
        Http::assertNothingSent();
    }

    public function test_dispatch_is_idempotent_per_transaction(): void
    {
        Http::fake([
            'bihaju.test/*' => Http::response(['transaction_code' => 'PTR-1', 'status' => 'success'], 201),
        ]);

        $result = app(CheckoutService::class)->execute($this->context());
        $transaction = $result->transaction;

        $service = app(BihajuService::class);

        // Kirim ulang record yang sama — tidak boleh membuat baris baru.
        $record = $service->record($transaction, $this->binding->fresh());
        $again = $service->record($transaction, $this->binding->fresh());

        $this->assertSame($record->id, $again->id);
        $this->assertSame(1, BihajuTransaction::withoutGlobalScopes()->count());
    }

    public function test_scheduler_retries_pending_records(): void
    {
        $failing = true;

        Http::fake(function () use (&$failing) {
            if ($failing) {
                throw new ConnectionException('down');
            }

            return Http::response([
                'transaction_code' => 'PTR-RETRY',
                'status' => 'success',
                'merchant_balance_after' => 1000,
            ], 201);
        });

        $result = app(CheckoutService::class)->execute($this->context());
        $record = BihajuTransaction::withoutGlobalScopes()
            ->where('transaction_id', $result->transaction->id)
            ->firstOrFail();

        $this->assertSame(BihajuTransaction::STATUS_PENDING, $record->status);
        $this->assertSame(1, $record->attempts);

        // Service kembali sehat → scheduler mengirim ulang.
        $failing = false;

        $this->artisan(DispatchBihajuPendingCommand::class)->assertSuccessful();

        $record->refresh();
        $this->assertSame(BihajuTransaction::STATUS_SUCCEEDED, $record->status);
        $this->assertSame('PTR-RETRY', $record->bihaju_transaction_code);
        $this->assertSame(2, $record->attempts);
    }

    public function test_settings_route_is_gated_by_bihaju_feature_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('settings.bihaju');

        $this->assertNotNull($route);
        $this->assertContains('feature:bihaju', $route->gatherMiddleware());

        $webhook = app('router')->getRoutes()->getByName('api.bihaju.webhook');
        $this->assertNotNull($webhook);
        $this->assertSame(['POST'], $webhook->methods());
    }

    public function test_queue_is_not_used_for_bihaju_submission(): void
    {
        // Pengiriman bersifat inline + retry terjadwal, bukan antrean queue
        // (BPOS tidak menjalankan worker queue).
        Queue::fake();

        Http::fake([
            'bihaju.test/*' => Http::response(['transaction_code' => 'PTR-Q', 'status' => 'success'], 201),
        ]);

        app(CheckoutService::class)->execute($this->context());

        Queue::assertNothingPushed();
        Http::assertSentCount(1);
    }
}
