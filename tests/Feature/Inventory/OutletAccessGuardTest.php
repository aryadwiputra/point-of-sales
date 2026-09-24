<?php

namespace Tests\Feature\Inventory;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerCampaign;
use App\Models\CustomerCampaignLog;
use App\Models\DineArea;
use App\Models\DineOrder;
use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\StockOpname;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OutletAccessGuardTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outletA;

    private Outlet $outletB;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outletA = Outlet::create(['code' => 'MAL', 'name' => 'Malabar', 'is_active' => true]);
        $this->outletB = Outlet::create(['code' => 'PUT', 'name' => 'Puter', 'is_active' => true]);

        $this->warehouseA = Warehouse::create([
            'code' => 'MAL', 'name' => 'Malabar', 'type' => 'branch',
            'is_active' => true, 'sort_order' => 0, 'outlet_id' => $this->outletA->id,
        ]);
        $this->warehouseB = Warehouse::create([
            'code' => 'PUT', 'name' => 'Puter', 'type' => 'branch',
            'is_active' => true, 'sort_order' => 1, 'outlet_id' => $this->outletB->id,
        ]);

        $this->user = User::factory()->create();
        $this->user->markEmailAsVerified();
        $this->user->outlets()->attach($this->outletA->id, ['is_default' => true]);
    }

    private function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]));
        }
    }

    private function makeTransaction(array $overrides = []): Transaction
    {
        return Transaction::create(array_merge([
            'cashier_id' => $this->user->id,
            'warehouse_id' => $this->warehouseB->id,
            'invoice' => 'INV-GUARD-'.Str::upper(Str::random(10)),
            'cash' => 0,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 10000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'discount_approval_status' => 'pending',
        ], $overrides));
    }

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Cust '.Str::random(5),
            'no_telp' => '628'.Str::random(9),
            'address' => 'Jl. Test',
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Kategori '.Str::random(5),
            'image' => 'categories/test.jpg',
            'description' => 'Kategori test',
        ]);
    }

    private function makeProduct(int $stock = 10): Product
    {
        return Product::create([
            'title' => 'Produk '.Str::random(5),
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'barcode' => 'BC-'.Str::upper(Str::random(8)),
            'buy_price' => 1000,
            'sell_price' => 1500,
            'stock' => $stock,
            'image' => 'products/test.jpg',
            'description' => 'Produk test',
            'tax_rate' => 0,
            'category_id' => $this->makeCategory()->id,
        ]);
    }

    public function test_export_transactions_only_includes_accessible_outlets(): void
    {
        $this->grant('transactions-access');

        $this->makeTransaction(['invoice' => 'INV-OWNED-A', 'warehouse_id' => $this->warehouseA->id]);
        $this->makeTransaction(['invoice' => 'INV-BLOCKED-B', 'warehouse_id' => $this->warehouseB->id]);

        $response = $this->actingAs($this->user)->get(route('export.transactions'));
        $response->assertOk();

        $content = $this->readStreamedSpreadsheet($response);
        $this->assertStringContainsString('INV-OWNED-A', $content);
        $this->assertStringNotContainsString('INV-BLOCKED-B', $content);
    }

    public function test_discount_approval_rejects_transaction_from_another_outlet(): void
    {
        $this->grant('discounts-approve');
        $transaction = $this->makeTransaction();

        $this->actingAs($this->user)
            ->post(route('discount-approvals.approve', $transaction))
            ->assertNotFound();

        $this->assertSame('pending', $transaction->fresh()->discount_approval_status);
    }

    public function test_pricing_rule_destroy_rejects_rule_from_another_outlet(): void
    {
        $this->grant('pricing-rules-delete');
        $rule = PricingRule::create([
            'name' => 'Rule B',
            'outlet_id' => $this->outletB->id,
            'kind' => PricingRule::KIND_STANDARD_DISCOUNT,
            'is_active' => true,
            'priority' => 0,
            'target_type' => PricingRule::TARGET_ALL,
            'customer_scope' => PricingRule::SCOPE_ALL,
            'discount_type' => PricingRule::TYPE_PERCENTAGE,
            'discount_value' => 10,
        ]);

        $this->actingAs($this->user)
            ->delete(route('pricing-rules.destroy', $rule))
            ->assertNotFound();

        $this->assertDatabaseHas('pricing_rules', ['id' => $rule->id]);
    }

    public function test_stock_opname_store_item_rejects_other_outlet_warehouse(): void
    {
        $this->grant('stock-opnames-create');
        $opname = StockOpname::create([
            'code' => 'SO-'.Str::upper(Str::random(8)),
            'warehouse_id' => $this->warehouseB->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);
        $product = $this->makeProduct();

        $this->actingAs($this->user)
            ->post(route('stock-opnames.items.store', $opname), ['product_id' => $product->id])
            ->assertForbidden();

        $this->assertDatabaseCount('stock_opname_items', 0);
    }

    public function test_dine_order_index_hides_other_outlet_orders(): void
    {
        $this->grant('dine-orders-access');

        $areaA = DineArea::create(['name' => 'Area A', 'outlet_id' => $this->outletA->id, 'is_active' => true]);
        $areaB = DineArea::create(['name' => 'Area B', 'outlet_id' => $this->outletB->id, 'is_active' => true]);
        $tableA = DiningTable::create(['dine_area_id' => $areaA->id, 'name' => 'A1', 'is_active' => true]);
        $tableB = DiningTable::create(['dine_area_id' => $areaB->id, 'name' => 'B1', 'is_active' => true]);

        DineOrder::create(['dine_table_id' => $tableA->id, 'status' => DineOrder::STATUS_SUBMITTED, 'payment_option' => DineOrder::PAY_AT_COUNTER, 'subtotal' => 10000, 'item_count' => 1]);
        DineOrder::create(['dine_table_id' => $tableB->id, 'status' => DineOrder::STATUS_SUBMITTED, 'payment_option' => DineOrder::PAY_AT_COUNTER, 'subtotal' => 20000, 'item_count' => 1]);

        $this->actingAs($this->user)
            ->get(route('dine-orders.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/DineIn/Orders/Index')
                ->has('orders', 1)
                ->where('orders.0.dine_table_id', $tableA->id));
    }

    public function test_dine_order_reject_rejects_other_outlet_order(): void
    {
        $this->grant('dine-orders-process');

        $areaB = DineArea::create(['name' => 'Area B', 'outlet_id' => $this->outletB->id, 'is_active' => true]);
        $tableB = DiningTable::create(['dine_area_id' => $areaB->id, 'name' => 'B1', 'is_active' => true]);
        $order = DineOrder::create(['dine_table_id' => $tableB->id, 'status' => DineOrder::STATUS_SUBMITTED, 'payment_option' => DineOrder::PAY_AT_COUNTER, 'subtotal' => 20000, 'item_count' => 1]);

        $this->actingAs($this->user)
            ->post(route('dine-orders.reject', $order), ['reason' => 'stok habis'])
            ->assertNotFound();

        $this->assertSame(DineOrder::STATUS_SUBMITTED, $order->fresh()->status);
    }

    public function test_crm_campaign_log_mark_sent_rejects_other_outlet_campaign(): void
    {
        $this->grant('crm-campaigns-update');

        $customer = $this->makeCustomer();
        $campaign = CustomerCampaign::create([
            'name' => 'Campaign B',
            'outlet_id' => $this->outletB->id,
            'type' => CustomerCampaign::TYPE_INVOICE_SHARE,
            'status' => CustomerCampaign::STATUS_READY,
            'channel' => 'internal',
            'created_by' => $this->user->id,
        ]);
        $log = CustomerCampaignLog::create([
            'customer_campaign_id' => $campaign->id,
            'customer_id' => $customer->id,
            'channel' => 'internal',
            'status' => CustomerCampaignLog::STATUS_READY_TO_SEND,
        ]);

        $this->actingAs($this->user)
            ->post(route('crm-campaign-logs.mark-sent', $log))
            ->assertNotFound();

        $this->assertSame(CustomerCampaignLog::STATUS_READY_TO_SEND, $log->fresh()->status);
    }

    public function test_crm_share_transaction_rejects_other_outlet_transaction(): void
    {
        $this->grant('crm-campaigns-create');
        $transaction = $this->makeTransaction(['customer_id' => $this->makeCustomer()->id]);

        $this->actingAs($this->user)
            ->post(route('transactions.share-campaign', $transaction))
            ->assertNotFound();

        $this->assertDatabaseCount('customer_campaigns', 0);
    }

    public function test_cashier_shift_force_close_permission_only_sees_accessible_outlet_shifts(): void
    {
        $this->grant('cashier-shifts-access', 'cashier-shifts-force-close');

        $otherUser = User::factory()->create();
        $otherUser->outlets()->attach($this->outletB->id, ['is_default' => true]);

        CashierShift::create([
            'user_id' => $this->user->id,
            'warehouse_id' => $this->warehouseA->id,
            'opened_by' => $this->user->id,
            'opened_at' => now(),
            'opening_cash' => 0,
            'expected_cash' => 0,
            'status' => 'open',
        ]);
        CashierShift::create([
            'user_id' => $otherUser->id,
            'warehouse_id' => $this->warehouseB->id,
            'opened_by' => $this->user->id,
            'opened_at' => now(),
            'opening_cash' => 0,
            'expected_cash' => 0,
            'status' => 'open',
        ]);

        $this->actingAs($this->user)
            ->get(route('cashier-shifts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/CashierShifts/Index')
                ->has('shifts.data', 1)
                ->where('shifts.data.0.warehouse.id', $this->warehouseA->id));
    }

    public function test_bank_account_scope_without_active_outlet_returns_only_global_accounts(): void
    {
        $this->grant('payment-settings-access');

        BankAccount::create(['bank_name' => 'Global', 'account_number' => '1', 'account_name' => 'G', 'is_active' => true, 'outlet_id' => null, 'sort_order' => 0]);
        BankAccount::create(['bank_name' => 'Outlet B', 'account_number' => '2', 'account_name' => 'B', 'is_active' => true, 'outlet_id' => $this->outletB->id, 'sort_order' => 1]);

        $scoped = BankAccount::query()->forOutlet(null)->pluck('bank_name')->all();

        $this->assertSame(['Global'], $scoped);
    }

    public function test_audit_log_index_and_show_hide_other_outlet_actor_logs(): void
    {
        $this->grant('audit-logs-access');

        $otherUser = User::factory()->create();
        $otherUser->outlets()->attach($this->outletB->id, ['is_default' => true]);

        $visible = AuditLog::create(['user_id' => $this->user->id, 'event' => 'login', 'module' => 'auth', 'description' => 'visible']);
        $hidden = AuditLog::create(['user_id' => $otherUser->id, 'event' => 'login', 'module' => 'auth', 'description' => 'hidden']);

        $this->actingAs($this->user)
            ->get(route('audit-logs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/AuditLogs/Index')
                ->has('auditLogs.data', 1)
                ->where('auditLogs.data.0.id', $visible->id));

        $this->actingAs($this->user)
            ->get(route('audit-logs.show', $hidden))
            ->assertNotFound();
    }

    private function readStreamedSpreadsheet($response): string
    {
        $path = $response->getFile()->getPathname();
        $spreadsheet = IOFactory::load($path);
        $writer = new Csv($spreadsheet);
        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }
}
