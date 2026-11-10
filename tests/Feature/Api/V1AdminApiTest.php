<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\PriceList;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class V1AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->markEmailAsVerified();
        $this->user->assignRole(Role::findOrCreate('super-admin', 'web'));

        Sanctum::actingAs($this->user, ['*']);
    }

    private function confirmPassword(): void
    {
        $this->postJson('/api/v1/auth/confirm-password', ['password' => 'password'])
            ->assertOk();
    }

    // --- Permissions ---

    public function test_permissions_index_lists_permissions(): void
    {
        $this->getJson('/api/v1/permissions')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    // --- Roles ---

    public function test_roles_index_lists_roles(): void
    {
        $role = Role::findOrCreate('auditor-'.uniqid(), 'web');

        $this->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonFragment(['name' => $role->name]);
    }

    public function test_role_store_requires_step_up(): void
    {
        $this->postJson('/api/v1/roles', ['name' => 'role-x'])
            ->assertStatus(423)
            ->assertJsonPath('step_up_required', true);
    }

    public function test_role_store_creates_role_after_confirmation(): void
    {
        $this->confirmPassword();
        $permission = Permission::findOrCreate('role-test-'.uniqid(), 'web');

        $this->postJson('/api/v1/roles', [
            'name' => 'role-new-'.uniqid(),
            'selectedPermission' => [$permission->name],
        ])->assertStatus(201);
    }

    public function test_role_update_and_destroy(): void
    {
        $this->confirmPassword();
        $permission = Permission::findOrCreate('role-test-'.uniqid(), 'web');
        $role = Role::findOrCreate('role-edit-'.uniqid(), 'web');

        $this->putJson("/api/v1/roles/{$role->id}", [
            'name' => 'role-renamed-'.uniqid(),
            'selectedPermission' => [$permission->name],
        ])->assertOk();

        $this->deleteJson("/api/v1/roles/{$role->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_role_update_blocks_super_admin(): void
    {
        $this->confirmPassword();
        $role = Role::findOrCreate('super-admin', 'web');

        $this->putJson("/api/v1/roles/{$role->id}", ['name' => 'hacked'])
            ->assertForbidden();
    }

    // --- Users ---

    public function test_users_index_lists_users(): void
    {
        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_user_store_creates_user(): void
    {
        $this->confirmPassword();
        $roleName = 'cashier-'.uniqid();
        Role::findOrCreate($roleName, 'web');

        $this->postJson('/api/v1/users', [
            'name' => 'Kasir Baru',
            'email' => 'kasir-'.uniqid().'@example.com',
            'password' => 'password123',
            'selectedRoles' => [$roleName],
            'outlet_ids' => [],
        ])->assertStatus(201);

        $this->assertDatabaseHas('users', ['name' => 'Kasir Baru']);
    }

    public function test_user_destroy_blocks_self_delete(): void
    {
        $this->confirmPassword();

        $this->deleteJson("/api/v1/users/{$this->user->id}")
            ->assertStatus(422);
    }

    // --- Units ---

    public function test_units_crud(): void
    {
        $code = 'U-'.Str::upper(Str::random(6));

        $this->postJson('/api/v1/settings/units', [
            'code' => $code,
            'name' => 'Pieces',
            'symbol' => 'pcs',
        ])->assertStatus(201);

        $unit = Unit::where('code', $code)->firstOrFail();

        $this->getJson('/api/v1/settings/units')
            ->assertOk()
            ->assertJsonFragment(['code' => $code]);

        $this->putJson("/api/v1/settings/units/{$unit->id}", [
            'code' => $code,
            'name' => 'Piece',
            'symbol' => 'pc',
        ])->assertOk();

        $this->deleteJson("/api/v1/settings/units/{$unit->id}")
            ->assertStatus(204);
    }

    public function test_unit_destroy_rejected_when_used_by_product(): void
    {
        $unit = Unit::create(['code' => 'U-'.Str::upper(Str::random(6)), 'name' => 'Box', 'symbol' => 'box']);
        $category = Category::create(['name' => 'Kat', 'image' => '', 'description' => '']);

        Product::create([
            'title' => 'Produk Unit',
            'barcode' => 'BR-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'image' => '',
            'description' => '',
            'category_id' => $category->id,
            'buy_price' => 1000,
            'sell_price' => 2000,
            'stock' => 1,
            'tax_rate' => 0,
        ])->units()->attach($unit->id, ['buy_price' => 1000, 'sell_price' => 2000]);

        $this->deleteJson("/api/v1/settings/units/{$unit->id}")
            ->assertStatus(422);
    }

    // --- Outlets ---

    public function test_outlets_index_returns_accessible_outlets(): void
    {
        Outlet::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'is_active' => true]);

        $this->getJson('/api/v1/settings/outlets')
            ->assertOk()
            ->assertJsonFragment(['code' => 'OUT-A']);
    }

    public function test_outlet_store_creates_outlet_and_branch_warehouse(): void
    {
        $this->confirmPassword();

        $code = 'OUT-'.Str::upper(Str::random(4));

        $this->postJson('/api/v1/settings/outlets', [
            'code' => $code,
            'name' => 'Outlet Baru',
            'warehouse_code' => $code.'-WH',
            'warehouse_name' => 'Gudang Outlet Baru',
            'is_sales_enabled' => true,
        ])->assertStatus(201);

        $this->assertDatabaseHas('outlets', ['code' => $code]);
        $this->assertDatabaseHas('warehouses', ['code' => $code.'-WH', 'type' => 'branch']);
    }

    public function test_outlet_destroy_rejected_for_pusat(): void
    {
        $this->confirmPassword();
        $pusat = Outlet::create(['code' => 'PUSAT', 'name' => 'Pusat', 'is_active' => true]);

        $this->deleteJson("/api/v1/settings/outlets/{$pusat->id}")
            ->assertStatus(422);
    }

    // --- Bank Accounts ---

    public function test_bank_accounts_index_lists_accounts(): void
    {
        $this->confirmPassword();
        $this->postJson('/api/v1/settings/bank-accounts', [
            'bank_name' => 'BCA',
            'account_number' => '123456789',
            'account_name' => 'Toko Kita',
            'is_active' => true,
        ])->assertStatus(201);

        $this->getJson('/api/v1/settings/bank-accounts')
            ->assertOk()
            ->assertJsonFragment(['bank_name' => 'BCA']);
    }

    public function test_bank_account_crud_and_toggle(): void
    {
        $this->confirmPassword();

        $this->postJson('/api/v1/settings/bank-accounts', [
            'bank_name' => 'Mandiri',
            'account_number' => '987654321',
            'account_name' => 'Toko Mandiri',
            'is_active' => true,
        ])->assertStatus(201);

        $account = BankAccount::where('bank_name', 'Mandiri')->firstOrFail();

        $this->putJson("/api/v1/settings/bank-accounts/{$account->id}", [
            'bank_name' => 'Mandiri Update',
            'account_number' => '987654321',
            'account_name' => 'Toko Mandiri',
            'is_active' => true,
        ])->assertOk();

        $this->postJson("/api/v1/settings/bank-accounts/{$account->id}/toggle")
            ->assertOk();

        $this->assertDatabaseHas('bank_accounts', ['id' => $account->id, 'is_active' => false]);

        $this->deleteJson("/api/v1/settings/bank-accounts/{$account->id}")
            ->assertStatus(204);
    }

    public function test_bank_account_mutation_requires_step_up(): void
    {
        $this->postJson('/api/v1/settings/bank-accounts', [
            'bank_name' => 'BRI',
            'account_number' => '111',
            'account_name' => 'Toko BRI',
        ])->assertStatus(423);
    }

    // --- Price Lists ---

    public function test_price_list_crud_and_items(): void
    {
        $this->confirmPassword();

        $category = Category::create(['name' => 'Kat PL', 'image' => '', 'description' => '']);
        $product = Product::create([
            'title' => 'Produk PL',
            'barcode' => 'BR-PL-'.uniqid(),
            'sku' => 'SKU-PL-'.uniqid(),
            'image' => '',
            'description' => '',
            'category_id' => $category->id,
            'buy_price' => 1000,
            'sell_price' => 2000,
            'stock' => 5,
            'tax_rate' => 0,
        ]);

        $this->postJson('/api/v1/settings/price-lists', [
            'name' => 'Grosir',
            'slug' => 'grosir-'.uniqid(),
            'customer_scope' => 'all',
            'priority' => 1,
        ])->assertStatus(201);

        $list = PriceList::where('name', 'Grosir')->firstOrFail();

        $this->getJson("/api/v1/settings/price-lists/{$list->id}")
            ->assertOk()
            ->assertJsonPath('data.price_list.id', $list->id);

        $this->patchJson("/api/v1/settings/price-lists/{$list->id}/items", [
            'product_id' => $product->id,
            'price' => 1750,
        ])->assertOk();

        $this->assertDatabaseHas('price_list_items', [
            'price_list_id' => $list->id,
            'product_id' => $product->id,
            'price' => 1750,
        ]);

        $this->deleteJson("/api/v1/settings/price-lists/{$list->id}/items/{$product->id}")
            ->assertStatus(204);

        $this->deleteJson("/api/v1/settings/price-lists/{$list->id}")
            ->assertStatus(204);
    }

    // --- Payment Settings ---

    public function test_payment_settings_show_and_update(): void
    {
        $this->getJson('/api/v1/settings/payments')
            ->assertOk()
            ->assertJsonStructure(['data' => ['setting', 'supported_gateways', 'webhook_urls']]);

        $this->confirmPassword();

        $this->putJson('/api/v1/settings/payments', [
            'default_gateway' => 'cash',
            'bank_transfer_enabled' => true,
            'midtrans_enabled' => false,
            'xendit_enabled' => false,
        ])->assertOk();
    }

    public function test_payment_settings_rejects_enabled_gateway_without_keys(): void
    {
        $this->confirmPassword();

        $this->putJson('/api/v1/settings/payments', [
            'default_gateway' => 'midtrans',
            'bank_transfer_enabled' => false,
            'midtrans_enabled' => true,
            'xendit_enabled' => false,
        ])->assertStatus(422);
    }

    // --- Store / target / printer / loyalty settings ---

    public function test_target_settings_show_and_update(): void
    {
        $this->getJson('/api/v1/settings/target')
            ->assertOk()
            ->assertJsonStructure(['data' => ['settings' => ['monthly_sales_target']]]);

        $this->postJson('/api/v1/settings/target', ['monthly_sales_target' => 5000000])
            ->assertOk();
    }

    public function test_store_profile_show_and_update(): void
    {
        $this->getJson('/api/v1/settings/store')
            ->assertOk()
            ->assertJsonStructure(['data' => ['settings']]);

        $this->postJson('/api/v1/settings/store', [
            'store_name' => 'Toko Lucif',
            'store_address' => 'Jl. Merdeka 1',
            'tax_default_rate' => 11,
        ])->assertOk();

        $this->assertDatabaseHas('settings', ['key' => 'store_name']);
    }

    public function test_printer_settings_show_and_update(): void
    {
        $this->getJson('/api/v1/settings/printer')
            ->assertOk()
            ->assertJsonStructure(['data' => ['settings' => ['printer_auto_print', 'printer_paper_size']]]);

        $this->postJson('/api/v1/settings/printer', [
            'printer_auto_print' => true,
            'printer_paper_size' => '58mm',
        ])->assertOk();
    }

    public function test_loyalty_settings_show_and_update_rejects_unordered_tiers(): void
    {
        $this->getJson('/api/v1/settings/loyalty')
            ->assertOk()
            ->assertJsonStructure(['data' => ['settings' => ['enable_earn', 'tiers']]]);

        $this->postJson('/api/v1/settings/loyalty', [
            'enable_earn' => true,
            'enable_redeem' => true,
            'earn_rate_amount' => 1000,
            'redeem_point_value' => 1,
            'tiers' => [
                'regular' => 0,
                'silver' => 500,
                'gold' => 100,
                'platinum' => 2000,
            ],
        ])->assertStatus(422);
    }

    public function test_loyalty_settings_update_succeeds_with_ordered_tiers(): void
    {
        $this->postJson('/api/v1/settings/loyalty', [
            'enable_earn' => true,
            'enable_redeem' => true,
            'earn_rate_amount' => 1000,
            'redeem_point_value' => 1,
            'tiers' => [
                'regular' => 0,
                'silver' => 500,
                'gold' => 1000,
                'platinum' => 2000,
            ],
        ])->assertOk();
    }

    public function test_whatsapp_settings_show(): void
    {
        $this->getJson('/api/v1/settings/whatsapp')
            ->assertOk()
            ->assertJsonStructure(['data' => ['settings' => ['wa_service_url', 'wa_enabled']]]);
    }

    public function test_whatsapp_update(): void
    {
        $this->postJson('/api/v1/settings/whatsapp', [
            'wa_enabled' => false,
            'wa_auto_reminder' => true,
        ])->assertOk();
    }

    // --- Pricing Rules ---

    public function test_pricing_rules_index_and_create_data(): void
    {
        $this->getJson('/api/v1/pricing-rules')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);

        $this->getJson('/api/v1/pricing-rules/create')
            ->assertOk()
            ->assertJsonStructure(['data' => ['products', 'categories', 'tierOptions', 'kindOptions']]);
    }

    public function test_pricing_rule_crud(): void
    {
        $this->postJson('/api/v1/pricing-rules', [
            'name' => 'Diskon Akhir Tahun',
            'kind' => 'standard_discount',
            'priority' => 1,
            'target_type' => 'all',
            'customer_scope' => 'all',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
        ])->assertStatus(201);

        $rule = PricingRule::where('name', 'Diskon Akhir Tahun')->firstOrFail();

        $this->getJson("/api/v1/pricing-rules/{$rule->id}")
            ->assertOk()
            ->assertJsonPath('data.rule.id', $rule->id);

        $this->putJson("/api/v1/pricing-rules/{$rule->id}", [
            'name' => 'Diskon Akhir Tahun 2',
            'kind' => 'standard_discount',
            'priority' => 2,
            'target_type' => 'all',
            'customer_scope' => 'all',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'is_active' => true,
        ])->assertOk();

        $this->deleteJson("/api/v1/pricing-rules/{$rule->id}")
            ->assertStatus(204);
    }

    public function test_pricing_rule_preview(): void
    {
        $category = Category::create(['name' => 'Kat PR', 'image' => '', 'description' => '']);
        $product = Product::create([
            'title' => 'Produk PR',
            'barcode' => 'BR-PR-'.uniqid(),
            'sku' => 'SKU-PR-'.uniqid(),
            'image' => '',
            'description' => '',
            'category_id' => $category->id,
            'buy_price' => 1000,
            'sell_price' => 5000,
            'stock' => 5,
            'tax_rate' => 0,
        ]);

        $this->postJson('/api/v1/pricing-rules/preview', [
            'name' => 'Preview Rule',
            'kind' => 'standard_discount',
            'priority' => 1,
            'target_type' => 'product',
            'product_id' => $product->id,
            'customer_scope' => 'all',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'preview_quantity_multiplier' => 1,
        ])->assertOk();
    }

    public function test_pricing_rules_requires_access_ability(): void
    {
        Sanctum::actingAs($this->user, ['products-access']);

        $this->getJson('/api/v1/pricing-rules')->assertForbidden();
    }

    // --- Abilities ---

    public function test_units_requires_access_ability(): void
    {
        Sanctum::actingAs($this->user, ['products-access']);

        $this->getJson('/api/v1/settings/units')->assertForbidden();
    }

    public function test_permissions_requires_access_ability(): void
    {
        Sanctum::actingAs($this->user, ['products-access']);

        $this->getJson('/api/v1/permissions')->assertForbidden();
    }

    // --- Audit Logs ---

    public function test_audit_logs_index_lists_logs(): void
    {
        $this->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonStructure(['data' => ['auditLogs', 'filters', 'users', 'modules', 'events']]);
    }

    public function test_audit_logs_show_returns_log(): void
    {
        $this->confirmPassword();
        $permission = Permission::findOrCreate('role-test-'.uniqid(), 'web');

        $this->postJson('/api/v1/roles', [
            'name' => 'role-audit-'.uniqid(),
            'selectedPermission' => [$permission->name],
        ])->assertStatus(201);

        $logId = AuditLog::query()->where('event', 'role.created')->latest('id')->value('id');
        $this->assertNotNull($logId);

        $this->getJson("/api/v1/audit-logs/{$logId}")
            ->assertOk()
            ->assertJsonPath('data.auditLog.id', $logId)
            ->assertJsonStructure(['data' => ['auditLog' => ['id', 'event', 'module', 'target_label', 'description', 'before', 'after']]]);
    }

    public function test_audit_logs_requires_access_ability(): void
    {
        Sanctum::actingAs($this->user, ['products-access']);

        $this->getJson('/api/v1/audit-logs')->assertForbidden();
    }

    // --- Reports ---

    public function test_sales_report(): void
    {
        $this->getJson('/api/v1/reports/sales')
            ->assertOk()
            ->assertJsonStructure(['data' => ['transactions', 'summary', 'filters', 'cashiers', 'customers', 'warehouses']]);
    }

    public function test_profit_report(): void
    {
        $this->getJson('/api/v1/reports/profits')
            ->assertOk()
            ->assertJsonStructure(['data' => ['transactions', 'summary', 'filters', 'cashiers', 'customers', 'warehouses']]);
    }

    public function test_insights_report(): void
    {
        $this->getJson('/api/v1/reports/insights')
            ->assertOk()
            ->assertJsonStructure(['data' => ['summary', 'salesByHour', 'salesByDay', 'topSellingProducts', 'lowPerformingProducts', 'marginByProduct', 'marginByCategory', 'cashierPerformance', 'repeatCustomerMetrics', 'stockCoverage', 'promoMonitor', 'loyaltyPerformance', 'crmOperations']]);
    }

    public function test_reports_require_access_ability(): void
    {
        Sanctum::actingAs($this->user, ['products-access']);

        $this->getJson('/api/v1/reports/sales')->assertForbidden();
        $this->getJson('/api/v1/reports/profits')->assertForbidden();
        $this->getJson('/api/v1/reports/insights')->assertForbidden();
    }
}
