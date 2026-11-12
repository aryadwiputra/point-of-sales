<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use App\Services\CheckoutService;
use App\Services\Finance\FinanceEventEmitter;
use App\Support\AddonCodes;
use App\Support\Checkout\CheckoutContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FinanceEventEmitterTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        config([
            'finance.url' => 'https://finance.test',
            'finance.bridge_secret' => 'bridge-secret',
            'finance.sso_secret' => 'sso-secret',
        ]);

        $this->cashier = User::where('email', 'cashier@gmail.com')->first();
        $this->cashier->markEmailAsVerified();

        $this->tenant = Tenant::create([
            'name' => 'Toko Finance',
            'subdomain' => 'finemit-'.uniqid(),
            'status' => 'active',
        ]);

        $category = Category::create([
            'name' => 'Kategori Finance',
            'image' => 'categories/fin.jpg',
            'description' => 'Kategori test finance',
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'GUD-FIN-'.uniqid(),
            'name' => 'Gudang Finance',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->product = Product::create([
            'title' => 'Produk Finance',
            'sku' => 'SKU-FIN-'.uniqid(),
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 100,
            'image' => 'products/fin.jpg',
            'barcode' => 'BC-FIN-'.uniqid(),
            'description' => 'Produk test finance',
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

        $this->actingAs($this->cashier);
    }

    protected function activateFinanceAddon(): void
    {
        TenantAddon::create([
            'tenant_id' => $this->tenant->id,
            'code' => AddonCodes::FINANCE,
            'status' => TenantAddon::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);
    }

    protected function withTenant(Transaction $transaction): Transaction
    {
        $transaction->forceFill(['tenant_id' => $this->tenant->id])->save();

        return $transaction->fresh();
    }

    protected function paidCheckoutTransaction(): Transaction
    {
        $this->post(route('transactions.addToCart'), [
            'product_id' => $this->product->id,
            'sell_price' => 10000,
            'qty' => 1,
        ]);

        $result = app(CheckoutService::class)->execute(new CheckoutContext(
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
            cashAmount: 10000,
            paymentGateway: null,
            useTenders: false,
            tenderInput: [],
            outlet: null,
            bankAccountId: null,
        ));

        $this->assertSame('paid', $result->transaction->payment_status);

        return $this->withTenant($result->transaction);
    }

    public function test_emits_sale_completed_with_correct_payload_signature_and_idempotency_key(): void
    {
        Http::fake([
            'https://finance.test/api/v1/events' => Http::response(['message' => 'ok'], 201),
        ]);
        $this->activateFinanceAddon();

        $transaction = $this->paidCheckoutTransaction();
        app(FinanceEventEmitter::class)->emitSaleCompleted($transaction);

        Http::assertSent(function ($request) use ($transaction) {
            $data = $request->data();
            $body = $request->body();
            $expectedSig = hash_hmac('sha256', $body, 'bridge-secret');

            return $request->url() === 'https://finance.test/api/v1/events'
                && $data['event'] === 'sale.completed'
                && $data['tenant_id'] === $this->tenant->id
                && $data['source_document_id'] === $transaction->id
                && $data['idempotency_key'] === sprintf('bpos:sale.completed:%d:%d', $this->tenant->id, $transaction->id)
                && (float) $data['amount'] === 10000.0
                && $request->hasHeader('X-Finance-Signature', $expectedSig);
        });
    }

    public function test_does_not_emit_when_addon_inactive(): void
    {
        Http::fake();

        $transaction = Transaction::create([
            'cashier_id' => $this->cashier->id,
            'cashier_shift_id' => $this->cashier->activeCashierShift->id ?? 1,
            'warehouse_id' => $this->warehouse->id,
            'invoice' => 'TRX-NO-ADDON-1',
            'cash' => 10000,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 10000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'tenant_id' => $this->tenant->id,
        ]);

        app(FinanceEventEmitter::class)->emitSaleCompleted($transaction);
        Http::assertNothingSent();
    }

    public function test_does_not_emit_when_unconfigured(): void
    {
        Http::fake();
        $this->activateFinanceAddon();
        config([
            'finance.url' => '',
        ]);

        $transaction = $this->paidCheckoutTransaction();
        app(FinanceEventEmitter::class)->emitSaleCompleted($transaction);
        Http::assertNothingSent();
    }

    public function test_emit_is_non_blocking_on_failure(): void
    {
        Http::fake(fn () => throw new \RuntimeException('network down'));
        $this->activateFinanceAddon();

        $transaction = $this->paidCheckoutTransaction();

        // Must not throw.
        app(FinanceEventEmitter::class)->emitSaleCompleted($transaction);

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'payment_status' => 'paid']);
    }

    public function test_emits_payment_received_on_receivable_payment(): void
    {
        Http::fake([
            'https://finance.test/api/v1/events' => Http::response(['message' => 'ok'], 201),
        ]);
        $this->activateFinanceAddon();

        $customer = Customer::create(['name' => 'Cust', 'no_telp' => '08123', 'address' => 'Addr']);
        $transaction = Transaction::create([
            'cashier_id' => $this->cashier->id,
            'cashier_shift_id' => $this->cashier->activeCashierShift->id ?? 1,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'invoice' => 'TRX-TEST-REC-1',
            'cash' => 0,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 10000,
            'payment_method' => 'pay_later',
            'payment_status' => 'unpaid',
        ]);
        $this->withTenant($transaction);

        $receivable = Receivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'transaction_id' => $transaction->id,
            'invoice' => $transaction->invoice,
            'total' => 10000,
            'paid' => 0,
            'status' => 'unpaid',
        ]);

        $payment = ReceivablePayment::create([
            'receivable_id' => $receivable->id,
            'paid_at' => now()->toDateString(),
            'amount' => 5000,
            'method' => 'cash',
            'user_id' => $this->cashier->id,
        ]);

        app(FinanceEventEmitter::class)->emitReceivablePayment($payment);

        Http::assertSent(function ($request) use ($payment) {
            $data = $request->data();
            $body = $request->body();
            $expectedSig = hash_hmac('sha256', $body, 'bridge-secret');

            return $data['event'] === 'payment.received'
                && $data['idempotency_key'] === sprintf('bpos:receivable_payment:%d:%d', $this->tenant->id, $payment->id)
                && $data['tenant_id'] === $this->tenant->id
                && (float) $data['amount'] === 5000.0
                && $request->hasHeader('X-Finance-Signature', $expectedSig);
        });
    }

    public function test_idempotency_key_stable_for_transaction_payment_context(): void
    {
        Http::fake([
            'https://finance.test/api/v1/events' => Http::response(['message' => 'ok'], 201),
        ]);
        $this->activateFinanceAddon();

        $transaction = Transaction::create([
            'cashier_id' => $this->cashier->id,
            'cashier_shift_id' => $this->cashier->activeCashierShift->id ?? 1,
            'warehouse_id' => $this->warehouse->id,
            'invoice' => 'TRX-TEST-CTX-1',
            'cash' => 0,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 10000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'paid',
            'payment_reference' => 'REF-123',
        ]);
        $this->withTenant($transaction);

        $emitter = app(FinanceEventEmitter::class);
        $emitter->emitPaymentReceivedForTransaction($transaction, 'webhook_midtrans');
        $emitter->emitPaymentReceivedForTransaction($transaction, 'bank_transfer_confirmed');

        Http::assertSentCount(2);
        Http::assertSent(function ($r) use ($transaction) {
            return $r['idempotency_key'] === sprintf('bpos:payment.received:%d:%d:REF-123', $this->tenant->id, $transaction->id);
        });
    }

    public function test_emits_bihaju_commission_event_decoupled_and_idempotent(): void
    {
        Http::fake([
            'https://finance.test/api/v1/events' => Http::response(['message' => 'ok'], 201),
        ]);
        $this->activateFinanceAddon();

        $record = \App\Models\BihajuTransaction::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'binding_id' => null,
            'transaction_id' => null,
            'external_ref' => 'BPOS-'.$this->tenant->id.'-77',
            'amount' => 10000,
            'status' => \App\Models\BihajuTransaction::STATUS_SUCCEEDED,
            'breakdown' => ['merchant_commission' => 500, 'platform_fee' => 50, 'member_cashback' => 100],
            'submitted_at' => now(),
        ]);

        app(FinanceEventEmitter::class)->emitBihajuCommission($record);

        Http::assertSent(function ($request) use ($record) {
            $data = $request->data();
            return $request->url() === 'https://finance.test/api/v1/events'
                && $data['event'] === 'bihaju.commission'
                && $data['tenant_id'] === $this->tenant->id
                && $data['idempotency_key'] === 'bpos:bihaju.commission:'.$this->tenant->id.':'.$record->external_ref
                && (float) $data['breakdown']['merchant_commission'] === 500.0
                && (float) $data['breakdown']['platform_fee'] === 50.0;
        });
    }

    public function test_bihaju_commission_not_emitted_when_finance_addon_inactive_or_zero_breakdown(): void
    {
        Http::fake();

        $record = \App\Models\BihajuTransaction::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'external_ref' => 'BPOS-'.$this->tenant->id.'-88',
            'amount' => 10000,
            'status' => \App\Models\BihajuTransaction::STATUS_SUCCEEDED,
            'breakdown' => ['merchant_commission' => 0, 'platform_fee' => 0],
        ]);

        $this->activateFinanceAddon();
        app(FinanceEventEmitter::class)->emitBihajuCommission($record);
        Http::assertNothingSent();
    }

}
