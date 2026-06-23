<?php

namespace Tests\Feature\AuditLogs;

use App\Models\AuditLog;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PaymentSetting;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'audit-logs-access',
            'products-create',
            'products-edit',
            'products-delete',
            'payment-settings-access',
            'payment-settings-update',
            'transactions-access',
            'transactions-confirm-payment',
            'stock-opnames-access',
            'stock-opnames-create',
            'stock-opnames-finalize',
            'cashier-shifts-access',
            'cashier-shifts-open',
            'cashier-shifts-close',
            'sales-returns-access',
            'sales-returns-create',
            'sales-returns-complete',
        ] as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_user_without_audit_log_permission_cannot_open_audit_log_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('audit-logs.index'))
            ->assertForbidden();
    }

    public function test_user_with_permission_can_view_audit_log_index(): void
    {
        $user = $this->createUserWithPermissions(['audit-logs-access']);

        AuditLog::create([
            'user_id' => $user->id,
            'event' => 'product.created',
            'module' => 'products',
            'target_label' => 'Produk A',
            'description' => 'Produk dibuat.',
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/AuditLogs/Index')
                ->where('auditLogs.data.0.event', 'product.created')
                ->where('auditLogs.data.0.target_label', 'Produk A'));
    }

    public function test_product_crud_and_price_change_create_audit_logs(): void
    {
        Storage::fake('local');

        $user = $this->createUserWithPermissions([
            'products-create',
            'products-edit',
            'products-delete',
        ]);

        $category = Category::create([
            'name' => 'Audit Product',
            'description' => 'Audit Product',
            'image' => 'audit-product.png',
        ]);

        $this->actingAs($user)->post(route('products.store'), [
            'image' => UploadedFile::fake()->image('product.png'),
            'barcode' => 'BRCD-'.Str::upper(Str::random(6)),
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'title' => 'Produk Audit',
            'description' => 'Produk Audit',
            'category_id' => $category->id,
            'buy_price' => 10000,
            'sell_price' => 15000,
            'stock' => 7,
        ])->assertRedirect(route('products.index'));

        $product = Product::firstOrFail();

        $this->actingAs($user)->put(route('products.update', $product), [
            'barcode' => $product->barcode,
            'sku' => $product->sku,
            'title' => 'Produk Audit Final',
            'description' => 'Produk Audit Baru',
            'category_id' => $category->id,
            'buy_price' => 12000,
            'sell_price' => 18000,
        ])->assertRedirect(route('products.index'));

        $this->actingAs($user)
            ->delete(route('products.destroy', $product->id))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'product.created',
            'module' => 'products',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'product.updated',
            'module' => 'products',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'product.price_updated',
            'module' => 'products',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'product.deleted',
            'module' => 'products',
        ]);
    }

    public function test_payment_setting_update_masks_secrets_in_audit_log(): void
    {
        $user = $this->createUserWithPermissions(['payment-settings-access', 'payment-settings-update']);

        PaymentSetting::create([
            'default_gateway' => 'cash',
            'midtrans_server_key' => 'old-server-key',
            'midtrans_client_key' => 'old-client-key',
        ]);

        $this->withSession($this->recentlyConfirmedSession())->actingAs($user)->put(route('settings.payments.update'), [
            'default_gateway' => 'cash',
            'bank_transfer_enabled' => true,
            'midtrans_enabled' => true,
            'midtrans_server_key' => 'new-server-key',
            'midtrans_client_key' => 'new-client-key',
            'midtrans_production' => false,
            'xendit_enabled' => false,
            'xendit_secret_key' => null,
            'xendit_public_key' => null,
            'xendit_callback_token' => null,
            'xendit_production' => false,
        ])->assertRedirect(route('settings.payments.edit'));

        $auditLog = AuditLog::query()
            ->where('event', 'payment.setting.updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('configured', $auditLog->before['midtrans_server_key']);
        $this->assertSame('updated', $auditLog->after['midtrans_server_key']);
        $this->assertNotEquals('new-server-key', $auditLog->after['midtrans_server_key']);
    }

    public function test_bank_account_and_cashier_shift_actions_are_audited(): void
    {
        $user = $this->createUserWithPermissions([
            'payment-settings-access',
            'payment-settings-update',
            'cashier-shifts-access',
            'cashier-shifts-open',
            'cashier-shifts-close',
        ]);

        $this->withSession($this->recentlyConfirmedSession())->actingAs($user)->post(route('settings.bank-accounts.store'), [
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_name' => 'PT Audit',
            'is_active' => true,
        ])->assertRedirect(route('settings.bank-accounts.index'));

        $bankAudit = AuditLog::query()
            ->where('event', 'bank_account.created')
            ->latest('id')
            ->first();

        $this->assertSame('******7890', $bankAudit->after['account_number_masked']);

        $this->actingAs($user)->post(route('cashier-shifts.store'), [
            'opening_cash' => 120000,
            'notes' => 'Shift audit',
        ]);

        $shift = CashierShift::firstOrFail();

        $this->actingAs($user)->post(route('cashier-shifts.close', $shift), [
            'actual_cash' => 120000,
            'close_notes' => 'Tutup normal',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'cashier_shift.opened',
            'module' => 'cashier_shifts',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'cashier_shift.closed',
            'module' => 'cashier_shifts',
        ]);
    }

    public function test_stock_opname_finalize_and_payment_confirmation_are_audited(): void
    {
        $user = $this->createUserWithPermissions([
            'stock-opnames-access',
            'stock-opnames-create',
            'stock-opnames-finalize',
            'transactions-access',
            'transactions-confirm-payment',
        ]);

        $product = $this->createProduct(stock: 10);
        $stockOpname = StockOpname::create([
            'code' => 'SO-AUDIT-001',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $stockOpname->id,
            'product_id' => $product->id,
            'system_stock' => 10,
            'physical_stock' => 8,
            'difference' => -2,
            'adjustment_reason' => 'Selisih audit',
        ]);

        $this->from(route('stock-opnames.show', $stockOpname))
            ->actingAs($user)
            ->post(route('stock-opnames.finalize', $stockOpname))
            ->assertRedirect(route('stock-opnames.show', $stockOpname));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'stock.opname.finalized',
            'module' => 'stock',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'stock.adjusted',
            'module' => 'stock',
        ]);

        $transaction = Transaction::create([
            'cashier_id' => $user->id,
            'customer_id' => null,
            'invoice' => 'TRX-'.Str::upper(Str::random(8)),
            'cash' => 0,
            'change' => 0,
            'discount' => 0,
            'shipping_cost' => 0,
            'grand_total' => 50000,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'pending',
        ]);

        $this->withSession($this->recentlyConfirmedSession())->actingAs($user)
            ->patch(route('transactions.confirm-payment', $transaction))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'transaction.payment_confirmed',
            'module' => 'transactions',
        ]);
    }

    public function test_sales_return_completion_is_audited(): void
    {
        $user = $this->createUserWithPermissions([
            'sales-returns-access',
            'sales-returns-create',
            'sales-returns-complete',
            'cashier-shifts-access',
            'cashier-shifts-open',
        ]);

        $shift = CashierShift::create([
            'user_id' => $user->id,
            'opened_by' => $user->id,
            'opened_at' => now(),
            'opening_cash' => 100000,
            'expected_cash' => 100000,
            'status' => CashierShift::STATUS_OPEN,
        ]);

        $customer = Customer::create([
            'name' => 'Customer Audit',
            'no_telp' => '0812345678',
            'address' => 'Jl. Audit',
        ]);

        $product = $this->createProduct(stock: 5, buyPrice: 20000, sellPrice: 30000);

        $transaction = Transaction::create([
            'cashier_id' => $user->id,
            'cashier_shift_id' => $shift->id,
            'customer_id' => $customer->id,
            'invoice' => 'TRX-'.Str::upper(Str::random(8)),
            'cash' => 30000,
            'change' => 0,
            'discount' => 0,
            'shipping_cost' => 0,
            'grand_total' => 30000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $detail = $transaction->details()->create([
            'product_id' => $product->id,
            'qty' => 1,
            'price' => 30000,
        ]);

        $salesReturn = SalesReturn::create([
            'code' => 'SR-'.Str::upper(Str::random(6)),
            'transaction_id' => $transaction->id,
            'customer_id' => $customer->id,
            'cashier_id' => $user->id,
            'status' => 'draft',
            'return_type' => 'refund_cash',
            'refund_amount' => 30000,
            'credited_amount' => 0,
            'total_return_amount' => 30000,
            'notes' => 'Retur audit',
        ]);

        $salesReturn->items()->create([
            'transaction_detail_id' => $detail->id,
            'product_id' => $product->id,
            'qty_sold' => 1,
            'qty_return' => 1,
            'unit_price' => 30000,
            'subtotal' => 30000,
            'subtotal_return' => 30000,
            'return_reason' => 'Barang cacat',
            'restock_to_inventory' => true,
        ]);

        $this->actingAs($user)
            ->post(route('sales-returns.complete', $salesReturn))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'sales_return.completed',
            'module' => 'sales_returns',
        ]);
    }

    private function createUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function createProduct(int $stock = 10, int $buyPrice = 10000, int $sellPrice = 15000): Product
    {
        $category = Category::create([
            'name' => 'Kategori Audit '.Str::random(4),
            'description' => 'Kategori Audit',
            'image' => 'audit-category.png',
        ]);

        return Product::create([
            'category_id' => $category->id,
            'image' => 'audit-product.png',
            'barcode' => 'BRCD-'.Str::upper(Str::random(8)),
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'title' => 'Produk Audit '.Str::random(4),
            'description' => 'Produk Audit',
            'buy_price' => $buyPrice,
            'sell_price' => $sellPrice,
            'stock' => $stock,
            'tax_rate' => 0,
        ]);
    }
}
