<?php

namespace Tests\Feature\Api;

use App\Models\BankAccount;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class V1DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Warehouse $warehouse;

    private Category $category;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create();
        $this->cashier->markEmailAsVerified();

        $this->warehouse = Warehouse::create([
            'code' => 'WH-1',
            'name' => 'Gudang Utama',
            'status' => 'active',
        ]);

        $this->category = Category::create([
            'name' => 'Kategori Test',
            'image' => '',
            'description' => '',
        ]);

        $this->product = Product::create([
            'title' => 'Produk API',
            'barcode' => 'API-001',
            'sku' => 'SKU-API-001',
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

        Sanctum::actingAs($this->cashier, ['*']);
    }

    private function openShift(): CashierShift
    {
        return app(CashierShiftService::class)->openShift(
            cashier: $this->cashier,
            actor: $this->cashier,
            openingCash: 100000,
            notes: null,
            warehouseId: $this->warehouse->id
        );
    }

    private function makeTransaction(array $overrides = []): Transaction
    {
        return Transaction::create(array_merge([
            'invoice' => 'INV-'.strtoupper(uniqid()).'X',
            'cashier_id' => $this->cashier->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => null,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'discount' => 0,
            'shipping_cost' => 0,
            'subtotal' => 20000,
            'grand_total' => 20000,
            'cash' => 20000,
            'change' => 0,
            'order_type' => 'in_store',
            'note' => null,
        ], $overrides));
    }

    private function makeBankAccount(): int
    {
        return BankAccount::create([
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_name' => 'Test',
            'is_active' => true,
        ])->id;
    }

    // --- Transactions ---

    public function test_transactions_index_returns_paginated_envelope(): void
    {
        $this->makeTransaction();

        $this->getJson('/api/v1/transactions')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    public function test_transactions_index_rejects_without_ability(): void
    {
        Sanctum::actingAs($this->cashier, ['products-access']);

        $this->getJson('/api/v1/transactions')->assertForbidden();
    }

    public function test_transaction_show_returns_detail(): void
    {
        $transaction = $this->makeTransaction();

        $this->getJson('/api/v1/transactions/'.$transaction->invoice)
            ->assertOk()
            ->assertJsonPath('data.invoice', $transaction->invoice);
    }

    public function test_transaction_show_404_for_unknown_invoice(): void
    {
        $this->getJson('/api/v1/transactions/INV-NOPE')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_transaction_status_returns_payment_status(): void
    {
        $transaction = $this->makeTransaction(['payment_status' => 'pending']);

        $this->getJson('/api/v1/transactions/'.$transaction->invoice.'/status')
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'pending');
    }

    public function test_transaction_qr_404_when_no_qr_string(): void
    {
        $transaction = $this->makeTransaction();

        $this->getJson('/api/v1/transactions/'.$transaction->invoice.'/qr')->assertNotFound();
    }

    public function test_transaction_qr_returns_svg_payload(): void
    {
        $transaction = $this->makeTransaction(['qr_string' => 'QRIS-TEST-PAYLOAD']);

        $this->getJson('/api/v1/transactions/'.$transaction->invoice.'/qr')
            ->assertOk()
            ->assertJsonPath('data.qr_string', 'QRIS-TEST-PAYLOAD')
            ->assertJsonStructure(['data' => ['qr_string', 'qr_svg']]);
    }

    public function test_confirm_payment_requires_step_up(): void
    {
        $transaction = $this->makeTransaction([
            'payment_method' => 'bank_transfer',
            'payment_status' => 'pending',
            'bank_account_id' => $this->makeBankAccount(),
        ]);

        $this->postJson('/api/v1/transactions/'.$transaction->id.'/confirm-payment')
            ->assertStatus(423)
            ->assertJsonPath('step_up_required', true);
    }

    public function test_confirm_payment_succeeds_after_password_confirmation(): void
    {
        $transaction = $this->makeTransaction([
            'payment_method' => 'bank_transfer',
            'payment_status' => 'pending',
            'bank_account_id' => $this->makeBankAccount(),
        ]);

        $this->postJson('/api/v1/auth/confirm-password', ['password' => 'password'])
            ->assertOk();

        $this->postJson('/api/v1/transactions/'.$transaction->id.'/confirm-payment')
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid');
    }

    public function test_confirm_payment_rejects_non_bank_transfer(): void
    {
        $transaction = $this->makeTransaction(['payment_status' => 'pending']);

        $this->postJson('/api/v1/transactions/'.$transaction->id.'/confirm-payment')
            ->assertStatus(423);
    }

    // --- Cashier Shifts ---

    public function test_cashier_shifts_index_returns_shifts(): void
    {
        $this->openShift();

        $this->getJson('/api/v1/cashier-shifts')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_cashier_shifts_store_opens_shift(): void
    {
        $this->postJson('/api/v1/cashier-shifts', [
            'opening_cash' => 100000,
        ])->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('cashier_shifts', [
            'user_id' => $this->cashier->id,
            'status' => 'open',
        ]);
    }

    public function test_cashier_shifts_show_returns_detail(): void
    {
        $shift = $this->openShift();

        $this->getJson('/api/v1/cashier-shifts/'.$shift->id)
            ->assertOk()
            ->assertJsonPath('data.id', $shift->id);
    }

    public function test_cashier_shifts_close_requires_step_up(): void
    {
        $shift = $this->openShift();

        $this->postJson('/api/v1/cashier-shifts/'.$shift->id.'/close', [
            'actual_cash' => 150000,
        ])->assertStatus(423)
            ->assertJsonPath('step_up_required', true);
    }

    public function test_cashier_shifts_add_cash_movement(): void
    {
        $shift = $this->openShift();

        $this->postJson('/api/v1/cashier-shifts/'.$shift->id.'/cash-movements', [
            'type' => 'in',
            'amount' => 50000,
            'note' => 'Tambahan kas',
        ])->assertCreated();

        $this->assertDatabaseHas('shift_cash_movements', [
            'cashier_shift_id' => $shift->id,
            'type' => 'in',
            'amount' => 50000,
        ]);
    }

    // --- Sales Returns ---

    public function test_sales_returns_index_requires_ability(): void
    {
        Sanctum::actingAs($this->cashier, ['transactions-access']);

        $this->getJson('/api/v1/sales-returns')->assertForbidden();
    }

    public function test_sales_returns_index_returns_empty(): void
    {
        $this->getJson('/api/v1/sales-returns')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    // --- Discount Approvals ---

    public function test_discount_approvals_pending_returns_empty(): void
    {
        $this->getJson('/api/v1/discount-approvals')
            ->assertOk()
            ->assertJsonStructure(['data' => ['pending']]);
    }

    public function test_discount_approvals_approve_updates_status(): void
    {
        $transaction = $this->makeTransaction([
            'discount' => 5000,
            'grand_total' => 15000,
            'payment_method' => 'cash',
            'payment_status' => 'pending_approval',
            'discount_approval_status' => 'pending',
        ]);

        $this->postJson('/api/v1/discount-approvals/'.$transaction->id.'/approve')
            ->assertOk()
            ->assertJsonPath('data.discount_approval_status', 'approved');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'discount_approval_status' => 'approved',
        ]);
    }

    public function test_discount_approvals_deny_readds_discount(): void
    {
        $transaction = $this->makeTransaction([
            'discount' => 5000,
            'grand_total' => 15000,
            'payment_method' => 'cash',
            'payment_status' => 'pending_approval',
            'discount_approval_status' => 'pending',
        ]);

        $this->postJson('/api/v1/discount-approvals/'.$transaction->id.'/deny', [
            'notes' => 'Tidak wajar',
        ])->assertOk()
            ->assertJsonPath('data.discount_approval_status', 'denied');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'discount_approval_status' => 'denied',
            'discount' => 0,
            'grand_total' => 20000,
        ]);
    }

    // --- Pricing Preview ---

    public function test_pricing_preview_requires_active_shift(): void
    {
        $this->getJson('/api/v1/transactions/pricing-preview')
            ->assertStatus(422);
    }
}
