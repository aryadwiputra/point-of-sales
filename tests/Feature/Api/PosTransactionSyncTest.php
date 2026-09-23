<?php

namespace Tests\Feature\Api;

use App\Models\BankAccount;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerVoucher;
use App\Models\PaymentSetting;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosTransactionSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosFixtures();
    }

    private function setUpPosFixtures(): void
    {
        $this->cashier = User::factory()->create();
        $this->warehouse = Warehouse::create([
            'code' => 'WH-SYNC',
            'name' => 'Gudang Sync',
            'status' => 'active',
        ]);
        $this->category = Category::create([
            'name' => 'Kategori Sync',
            'image' => '',
            'description' => '',
        ]);
        $this->product = Product::create([
            'title' => 'Produk Sync',
            'barcode' => 'SYNC-001',
            'sku' => 'SKU-SYNC-001',
            'image' => '',
            'description' => '',
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 50,
            'category_id' => $this->category->id,
            'tax_type' => 'exclusive',
            'tax_rate' => 0,
            'min_stock' => 0,
            'max_stock' => 100,
            'is_composite' => false,
        ]);
        $this->product->warehouses()->attach($this->warehouse->id, ['stock' => 50]);
    }

    protected function syncPayload(array $overrides = []): array
    {
        return array_merge([
            'client_uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 2, 'unit_id' => null],
            ],
            'cash' => 100000,
        ], $overrides);
    }

    public function test_sync_creates_transaction_with_server_side_pricing(): void
    {
        Sanctum::actingAs($this->cashier, ['*']);

        app(CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );

        // Client claims grand_total 1 — server must ignore it.
        $response = $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$this->syncPayload(['grand_total' => 1])],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.results.0.status', 'synced');

        $transaction = Transaction::first();
        $this->assertNotNull($transaction);
        $this->assertEquals(20000, $transaction->grand_total);
        $this->assertEquals(
            '550e8400-e29b-41d4-a716-446655440000',
            $transaction->client_uuid
        );
        $this->assertSame(1, $transaction->details()->count());
        $this->assertEquals(48, $this->product->fresh()->stock);
    }

    public function test_sync_is_idempotent_by_client_uuid(): void
    {
        Sanctum::actingAs($this->cashier, ['*']);

        app(CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );

        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$this->syncPayload()],
        ])->assertOk();

        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$this->syncPayload()],
        ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'duplicate');

        $this->assertSame(1, Transaction::count());
        $this->assertEquals(48, $this->product->fresh()->stock);
    }

    public function test_sync_fails_without_active_shift_and_keeps_no_data(): void
    {
        Sanctum::actingAs($this->cashier, ['*']);

        $response = $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$this->syncPayload()],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.results.0.status', 'failed');

        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, Cart::count());
        $this->assertEquals(50, $this->product->fresh()->stock);
    }

    public function test_sync_requires_authentication(): void
    {
        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$this->syncPayload()],
        ])->assertUnauthorized();
    }

    public function test_sync_validates_payload_structure(): void
    {
        Sanctum::actingAs($this->cashier, ['*']);

        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [
                ['client_uuid' => 'not-a-uuid', 'items' => []],
            ],
        ])->assertUnprocessable();
    }

    public function test_sync_sets_client_uuid_during_transaction_creation(): void
    {
        Sanctum::actingAs($this->cashier, ['*']);

        app(CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );

        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$this->syncPayload()],
        ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'synced');

        $transaction = Transaction::where(
            'client_uuid',
            '550e8400-e29b-41d4-a716-446655440000'
        )->first();

        $this->assertNotNull($transaction);
        $this->assertNotNull($transaction->created_at);
        $this->assertSame(1, Transaction::count());
    }

    public function test_duplicate_sync_does_not_create_extra_tender_or_receivable(): void
    {
        Sanctum::actingAs($this->cashier, ['*']);

        app(CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );

        $payload = $this->syncPayload();

        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$payload],
        ])->assertOk();

        $tendersBefore = \DB::table('transaction_tenders')->count();
        $receivablesBefore = \DB::table('receivables')->count();
        $detailsBefore = \DB::table('transaction_details')->count();

        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$payload],
        ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'duplicate');

        $this->assertSame($tendersBefore, \DB::table('transaction_tenders')->count());
        $this->assertSame($receivablesBefore, \DB::table('receivables')->count());
        $this->assertSame($detailsBefore, \DB::table('transaction_details')->count());
    }

    public function test_reused_client_uuid_with_different_payload_returns_conflict(): void
    {
        Sanctum::actingAs($this->cashier, ['*']);

        app(CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );

        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$this->syncPayload()],
        ])->assertOk();

        // Same UUID, materially different payload (different qty + cash).
        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$this->syncPayload([
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 5, 'unit_id' => null],
                ],
                'cash' => 200000,
            ])],
        ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'conflict');

        $this->assertSame(1, Transaction::count());
        $this->assertEquals(48, $this->product->fresh()->stock);
    }

    public function test_duplicate_sync_without_pay_later_does_not_create_receivable(): void
    {
        Sanctum::actingAs($this->cashier, ['*']);

        app(CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );

        $payload = $this->syncPayload();

        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$payload],
        ])->assertOk();

        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$payload],
        ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'duplicate');

        $this->assertSame(1, Transaction::count());
        $this->assertSame(0, \DB::table('receivables')->count());
    }

    public function test_sync_applies_voucher_and_persists_order_type_and_note(): void
    {
        Sanctum::actingAs($this->cashier, ['*']);

        app(CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );

        $customer = Customer::create([
            'name' => 'Member Sync',
            'no_telp' => '628123456789',
            'address' => 'Jl. Sync',
        ]);
        $voucher = CustomerVoucher::create([
            'customer_id' => $customer->id,
            'code' => 'VCR-SYNC',
            'name' => 'Voucher Sync',
            'discount_type' => CustomerVoucher::TYPE_FIXED_AMOUNT,
            'discount_value' => 5000,
            'minimum_order' => 0,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$this->syncPayload([
                'client_uuid' => '660e8400-e29b-41d4-a716-446655440001',
                'customer_id' => $customer->id,
                'customer_voucher_id' => $voucher->id,
                'order_type' => 'delivery',
                'note' => 'Titip kopi bubuk',
            ])],
        ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'synced');

        $transaction = Transaction::where(
            'client_uuid',
            '660e8400-e29b-41d4-a716-446655440001'
        )->first();

        $this->assertNotNull($transaction);
        $this->assertEquals(15000, $transaction->grand_total);
        $this->assertEquals(5000, $transaction->customer_voucher_discount);
        $this->assertEquals('VCR-SYNC', $transaction->customer_voucher_code);
        $this->assertEquals('delivery', $transaction->order_type);
        $this->assertEquals('Titip kopi bubuk', $transaction->note);
        $this->assertTrue((bool) $voucher->fresh()->is_used);
        $this->assertEquals($transaction->id, $voucher->fresh()->used_transaction_id);
    }

    public function test_sync_marks_voucher_used_on_synced_transaction(): void
    {
        Sanctum::actingAs($this->cashier, ['*']);

        app(CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );

        $customer = Customer::create([
            'name' => 'Member Sync 2',
            'no_telp' => '628123456780',
            'address' => 'Jl. Sync 2',
        ]);
        $voucher = CustomerVoucher::create([
            'customer_id' => $customer->id,
            'code' => 'VCR-SYNC2',
            'name' => 'Voucher Sync 2',
            'discount_type' => CustomerVoucher::TYPE_FIXED_AMOUNT,
            'discount_value' => 5000,
            'minimum_order' => 0,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$this->syncPayload([
                'client_uuid' => '660e8400-e29b-41d4-a716-446655440002',
                'customer_id' => $customer->id,
                'customer_voucher_id' => $voucher->id,
            ])],
        ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'synced');

        $this->assertTrue((bool) $voucher->fresh()->is_used);
        $this->assertNotNull($voucher->fresh()->used_transaction_id);
    }

    public function test_sync_rejects_voucher_of_different_customer_without_transaction(): void
    {
        Sanctum::actingAs($this->cashier, ['*']);

        app(CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );

        $customer = Customer::create([
            'name' => 'Member Sync 3',
            'no_telp' => '628123456781',
            'address' => 'Jl. Sync 3',
        ]);
        $otherCustomer = Customer::create([
            'name' => 'Member Sync 4',
            'no_telp' => '628123456782',
            'address' => 'Jl. Sync 4',
        ]);
        $voucher = CustomerVoucher::create([
            'customer_id' => $otherCustomer->id,
            'code' => 'VCR-OTHER',
            'name' => 'Voucher Orang Lain',
            'discount_type' => CustomerVoucher::TYPE_FIXED_AMOUNT,
            'discount_value' => 5000,
            'minimum_order' => 0,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$this->syncPayload([
                'client_uuid' => '660e8400-e29b-41d4-a716-446655440003',
                'customer_id' => $customer->id,
                'customer_voucher_id' => $voucher->id,
            ])],
        ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'failed');

        $this->assertSame(0, Transaction::count());
        $this->assertSame(0, Cart::count());
        $this->assertFalse((bool) $voucher->fresh()->is_used);
    }

    public function test_sync_persists_shipping_cost_in_server_total(): void
    {
        Sanctum::actingAs($this->cashier, ['*']);

        app(CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );

        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$this->syncPayload([
                'client_uuid' => '660e8400-e29b-41d4-a716-446655440004',
                'shipping_cost' => 9000,
                'cash' => 109000,
            ])],
        ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'synced');

        $transaction = Transaction::where(
            'client_uuid',
            '660e8400-e29b-41d4-a716-446655440004'
        )->first();

        $this->assertNotNull($transaction);
        $this->assertEquals(9000, $transaction->shipping_cost);
        $this->assertEquals(29000, $transaction->grand_total);
    }

    public function test_sync_persists_bank_transfer_with_outlet_bank_account(): void
    {
        Sanctum::actingAs($this->cashier, ['*']);

        app(CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );

        $bankAccount = BankAccount::create([
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_name' => 'Toko Sync',
            'is_active' => true,
        ]);

        PaymentSetting::create([
            'default_gateway' => 'bank_transfer',
            'bank_transfer_enabled' => true,
        ]);

        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$this->syncPayload([
                'client_uuid' => '660e8400-e29b-41d4-a716-446655440005',
                'payment_method' => 'bank_transfer',
                'bank_account_id' => $bankAccount->id,
                'cash' => 0,
            ])],
        ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'synced');

        $transaction = Transaction::where(
            'client_uuid',
            '660e8400-e29b-41d4-a716-446655440005'
        )->first();

        $this->assertNotNull($transaction);
        $this->assertEquals('bank_transfer', $transaction->payment_method);
        $this->assertEquals($bankAccount->id, $transaction->bank_account_id);
    }

    public function test_sync_rejects_unsupported_gateway_for_offline(): void
    {
        Sanctum::actingAs($this->cashier, ['*']);

        app(CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );

        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$this->syncPayload([
                'client_uuid' => '660e8400-e29b-41d4-a716-446655440006',
                'payment_method' => 'midtrans',
            ])],
        ])->assertUnprocessable();

        $this->assertSame(0, Transaction::count());
    }

    public function test_sync_pay_later_creates_receivable_with_due_date(): void
    {
        Sanctum::actingAs($this->cashier, ['*']);

        app(CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );

        $customer = Customer::create([
            'name' => 'Member Sync 5',
            'no_telp' => '628123456783',
            'address' => 'Jl. Sync 5',
        ]);

        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$this->syncPayload([
                'client_uuid' => '660e8400-e29b-41d4-a716-446655440007',
                'customer_id' => $customer->id,
                'pay_later' => true,
                'due_date' => now()->addDays(7)->toDateString(),
                'cash' => 0,
            ])],
        ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'synced');

        $transaction = Transaction::where(
            'client_uuid',
            '660e8400-e29b-41d4-a716-446655440007'
        )->first();

        $this->assertNotNull($transaction);
        $this->assertEquals('pay_later', $transaction->payment_method);

        $receivable = $transaction->receivable;
        $this->assertNotNull($receivable);
        $this->assertEquals(
            now()->addDays(7)->toDateString(),
            $receivable->due_date->toDateString()
        );
    }

    public function test_sync_ignores_client_grand_total_and_prices(): void
    {
        Sanctum::actingAs($this->cashier, ['*']);

        app(CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );

        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$this->syncPayload([
                'client_uuid' => '660e8400-e29b-41d4-a716-446655440008',
                'grand_total' => 1,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'qty' => 1,
                        'unit_id' => null,
                        'price' => 1,
                    ],
                ],
                'cash' => 999999,
            ])],
        ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'synced');

        $transaction = Transaction::where(
            'client_uuid',
            '660e8400-e29b-41d4-a716-446655440008'
        )->first();

        $this->assertNotNull($transaction);
        $this->assertEquals(10000, $transaction->grand_total);
    }

    public function test_sync_does_not_consume_live_cart(): void
    {
        Sanctum::actingAs($this->cashier, ['*']);

        app(CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );

        // Live cart created directly (not part of the offline payload).
        $liveCart = Cart::create([
            'cashier_id' => $this->cashier->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'unit_id' => null,
            'conversion_factor' => 1,
            'qty' => 1,
            'price' => 10000,
        ]);

        $this->postJson('/api/v1/pos/transactions/sync', [
            'transactions' => [$this->syncPayload([
                'client_uuid' => '770e8400-e29b-41d4-a716-446655440010',
            ])],
        ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'synced');

        // Synced transaction contains only the offline item (qty 2), not the live cart item.
        $transaction = Transaction::where(
            'client_uuid',
            '770e8400-e29b-41d4-a716-446655440010'
        )->first();
        $this->assertNotNull($transaction);
        $this->assertSame(1, $transaction->details()->count());
        $this->assertEquals(2, $transaction->details()->first()->qty);
        $this->assertEquals(20000, $transaction->grand_total);

        // Live cart survives untouched.
        $this->assertDatabaseHas('carts', ['id' => $liveCart->id, 'qty' => 1]);
    }
}
