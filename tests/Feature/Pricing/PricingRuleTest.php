<?php

namespace Tests\Feature\Pricing;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PricingRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'pricing-rules-access',
            'pricing-rules-create',
            'pricing-rules-update',
            'pricing-rules-delete',
            'transactions-access',
            'cashier-shifts-access',
            'cashier-shifts-open',
            'cashier-shifts-close',
        ] as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_authorized_user_can_create_pricing_rule(): void
    {
        $user = $this->createUserWithPermissions([
            'pricing-rules-access',
            'pricing-rules-create',
        ]);
        $category = Category::create([
            'name' => 'Minuman',
            'description' => 'Kategori uji',
            'image' => 'category.png',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('pricing-rules.store'), [
                'name' => 'Promo Minuman Pagi',
                'kind' => PricingRule::KIND_STANDARD_DISCOUNT,
                'is_active' => true,
                'priority' => 120,
                'target_type' => 'category',
                'category_id' => $category->id,
                'customer_scope' => 'all',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'starts_at' => now()->subHour()->format('Y-m-d\TH:i'),
                'ends_at' => now()->addHour()->format('Y-m-d\TH:i'),
                'notes' => 'Promo aktif pagi ini',
            ]);

        $response->assertRedirect(route('pricing-rules.index'));
        $this->assertDatabaseHas('pricing_rules', [
            'name' => 'Promo Minuman Pagi',
            'target_type' => 'category',
            'category_id' => $category->id,
            'discount_type' => 'percentage',
            'customer_scope' => 'all',
            'created_by' => $user->id,
        ]);
    }

    public function test_create_pricing_rule_ignores_hidden_relation_rows_from_form(): void
    {
        $user = $this->createUserWithPermissions([
            'pricing-rules-access',
            'pricing-rules-create',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('pricing-rules.store'), [
                'name' => 'Diskon Kasir',
                'kind' => PricingRule::KIND_STANDARD_DISCOUNT,
                'is_active' => true,
                'priority' => 100,
                'target_type' => 'all',
                'customer_scope' => 'all',
                'discount_type' => PricingRule::TYPE_PERCENTAGE,
                'discount_value' => 5,
                'preview_quantity_multiplier' => 1,
                'qty_breaks' => [
                    ['min_qty' => '3', 'discount_type' => PricingRule::TYPE_FIXED_PRICE, 'discount_value' => '', 'sort_order' => '0'],
                ],
                'bundle_items' => [
                    ['product_id' => '', 'quantity' => '1', 'sort_order' => '0'],
                    ['product_id' => '', 'quantity' => '1', 'sort_order' => '1'],
                ],
                'buy_get_items' => [
                    ['product_id' => '', 'role' => 'buy', 'quantity' => '1', 'sort_order' => '0'],
                    ['product_id' => '', 'role' => 'get', 'quantity' => '1', 'sort_order' => '1'],
                ],
            ]);

        $response->assertRedirect(route('pricing-rules.index'));
        $this->assertDatabaseHas('pricing_rules', [
            'name' => 'Diskon Kasir',
            'kind' => PricingRule::KIND_STANDARD_DISCOUNT,
            'discount_value' => 5,
        ]);
        $this->assertDatabaseMissing('pricing_rule_bundle_items', [
            'quantity' => 1,
        ]);
        $this->assertDatabaseMissing('pricing_rule_buy_get_items', [
            'quantity' => 1,
        ]);
    }

    public function test_create_buy_x_get_y_rule_does_not_require_discount_value_from_form(): void
    {
        $user = $this->createUserWithPermissions([
            'pricing-rules-access',
            'pricing-rules-create',
        ]);
        $buyProduct = $this->createProduct('Produk Beli');
        $getProduct = $this->createProduct('Produk Bonus');

        $response = $this
            ->actingAs($user)
            ->post(route('pricing-rules.store'), [
                'name' => 'Beli Satu Gratis Satu',
                'kind' => PricingRule::KIND_BUY_X_GET_Y,
                'is_active' => true,
                'priority' => 100,
                'target_type' => 'all',
                'customer_scope' => 'all',
                'preview_quantity_multiplier' => 1,
                'buy_get_items' => [
                    ['product_id' => $buyProduct->id, 'role' => 'buy', 'quantity' => '1', 'sort_order' => '0'],
                    ['product_id' => $getProduct->id, 'role' => 'get', 'quantity' => '1', 'sort_order' => '1'],
                ],
            ]);

        $response->assertRedirect(route('pricing-rules.index'));
        $this->assertDatabaseHas('pricing_rules', [
            'name' => 'Beli Satu Gratis Satu',
            'kind' => PricingRule::KIND_BUY_X_GET_Y,
            'discount_type' => PricingRule::TYPE_FIXED_AMOUNT,
            'discount_value' => 0,
        ]);
        $this->assertDatabaseHas('pricing_rule_buy_get_items', [
            'product_id' => $buyProduct->id,
            'role' => 'buy',
        ]);
        $this->assertDatabaseHas('pricing_rule_buy_get_items', [
            'product_id' => $getProduct->id,
            'role' => 'get',
        ]);
    }

    public function test_create_bundle_rule_ignores_hidden_discount_type_from_form(): void
    {
        $user = $this->createUserWithPermissions([
            'pricing-rules-access',
            'pricing-rules-create',
        ]);
        $productA = $this->createProduct('Produk Paket A');
        $productB = $this->createProduct('Produk Paket B');

        $response = $this
            ->actingAs($user)
            ->post(route('pricing-rules.store'), [
                'name' => 'Paket Hemat',
                'kind' => PricingRule::KIND_BUNDLE_PRICE,
                'is_active' => true,
                'priority' => 100,
                'target_type' => 'all',
                'customer_scope' => 'all',
                'discount_type' => PricingRule::TYPE_PERCENTAGE,
                'discount_value' => 100000,
                'preview_quantity_multiplier' => 1,
                'bundle_items' => [
                    ['product_id' => $productA->id, 'quantity' => '1', 'sort_order' => '0'],
                    ['product_id' => $productB->id, 'quantity' => '1', 'sort_order' => '1'],
                ],
            ]);

        $response->assertRedirect(route('pricing-rules.index'));
        $this->assertDatabaseHas('pricing_rules', [
            'name' => 'Paket Hemat',
            'kind' => PricingRule::KIND_BUNDLE_PRICE,
            'discount_type' => PricingRule::TYPE_FIXED_PRICE,
            'discount_value' => 100000,
        ]);
        $this->assertDatabaseHas('pricing_rule_bundle_items', [
            'product_id' => $productA->id,
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('pricing_rule_bundle_items', [
            'product_id' => $productB->id,
            'quantity' => 1,
        ]);
    }

    public function test_create_permission_user_can_run_pricing_rule_draft_preview(): void
    {
        $user = $this->createUserWithPermissions([
            'pricing-rules-create',
        ]);
        $this->createProduct('Produk Preview');

        $response = $this
            ->actingAs($user)
            ->postJson(route('pricing-rules.preview'), [
                'name' => 'Preview Diskon',
                'kind' => PricingRule::KIND_STANDARD_DISCOUNT,
                'is_active' => true,
                'priority' => 100,
                'target_type' => 'all',
                'customer_scope' => 'all',
                'discount_type' => PricingRule::TYPE_PERCENTAGE,
                'discount_value' => 10,
                'preview_quantity_multiplier' => 1,
                'bundle_items' => [
                    ['product_id' => '', 'quantity' => '1', 'sort_order' => '0'],
                    ['product_id' => '', 'quantity' => '1', 'sort_order' => '1'],
                ],
                'buy_get_items' => [
                    ['product_id' => '', 'role' => 'buy', 'quantity' => '1', 'sort_order' => '0'],
                    ['product_id' => '', 'role' => 'get', 'quantity' => '1', 'sort_order' => '1'],
                ],
            ]);

        $response->assertOk();
        $this->assertSame(6000, data_get($response->json(), 'data.summary.promo_discount_total'));
    }

    public function test_pricing_rule_draft_preview_uses_selected_product_target(): void
    {
        $user = $this->createUserWithPermissions([
            'pricing-rules-access',
        ]);
        $otherProduct = $this->createProduct('Produk Lain');
        $targetProduct = $this->createProduct('Produk Target');

        $response = $this
            ->actingAs($user)
            ->postJson(route('pricing-rules.preview'), [
                'name' => 'Preview Produk Target',
                'kind' => PricingRule::KIND_STANDARD_DISCOUNT,
                'is_active' => true,
                'priority' => 100,
                'target_type' => PricingRule::TARGET_PRODUCT,
                'product_id' => $targetProduct->id,
                'customer_scope' => 'all',
                'discount_type' => PricingRule::TYPE_PERCENTAGE,
                'discount_value' => 10,
                'preview_quantity_multiplier' => 1,
            ]);

        $response->assertOk();
        $this->assertCount(1, data_get($response->json(), 'data.items'));
        $this->assertSame($targetProduct->id, data_get($response->json(), 'data.items.0.product_id'));
        $this->assertNotSame($otherProduct->id, data_get($response->json(), 'data.items.0.product_id'));
        $this->assertSame(6000, data_get($response->json(), 'data.summary.promo_discount_total'));
    }

    public function test_pricing_preview_respects_customer_scope(): void
    {
        $cashier = $this->createUserWithPermissions([
            'transactions-access',
            'cashier-shifts-access',
            'cashier-shifts-open',
            'cashier-shifts-close',
        ]);
        $this->openShiftFor($cashier);
        $product = $this->createProduct();
        $customer = Customer::create([
            'name' => 'Registered Customer',
            'no_telp' => '62812345678',
            'address' => 'Jl. Uji Pelanggan',
        ]);

        Cart::create([
            'cashier_id' => $cashier->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => $product->sell_price,
        ]);

        PricingRule::create([
            'name' => 'Harga Member',
            'is_active' => true,
            'priority' => 200,
            'target_type' => 'product',
            'product_id' => $product->id,
            'customer_scope' => 'registered',
            'discount_type' => 'fixed_amount',
            'discount_value' => 10000,
        ]);

        $walkInResponse = $this
            ->actingAs($cashier)
            ->postJson(route('transactions.pricing-preview'), []);

        $registeredResponse = $this
            ->actingAs($cashier)
            ->postJson(route('transactions.pricing-preview'), [
                'customer_id' => $customer->id,
            ]);

        $walkInResponse->assertOk();
        $registeredResponse->assertOk();
        $this->assertSame(
            0,
            data_get($walkInResponse->json(), 'data.summary.promo_discount_total')
        );
        $this->assertSame(
            10000,
            data_get($registeredResponse->json(), 'data.summary.promo_discount_total')
        );
    }

    public function test_qty_break_preview_applies_wholesale_rule(): void
    {
        $cashier = $this->createUserWithPermissions([
            'transactions-access',
            'cashier-shifts-access',
            'cashier-shifts-open',
            'cashier-shifts-close',
        ]);
        $this->openShiftFor($cashier);
        $product = $this->createProduct();

        Cart::create([
            'cashier_id' => $cashier->id,
            'product_id' => $product->id,
            'qty' => 3,
            'price' => $product->sell_price * 3,
        ]);

        $rule = PricingRule::create([
            'name' => 'Harga Grosir Produk',
            'kind' => PricingRule::KIND_QTY_BREAK,
            'is_active' => true,
            'priority' => 250,
            'target_type' => 'product',
            'product_id' => $product->id,
            'customer_scope' => 'all',
            'discount_type' => 'fixed_price',
            'discount_value' => 0,
        ]);
        $rule->qtyBreaks()->create([
            'min_qty' => 3,
            'discount_type' => 'fixed_price',
            'discount_value' => 50000,
            'sort_order' => 0,
        ]);

        $response = $this
            ->actingAs($cashier)
            ->postJson(route('transactions.pricing-preview'), []);

        $response->assertOk();
        $this->assertSame(
            30000,
            data_get($response->json(), 'data.summary.promo_discount_total')
        );
        $this->assertSame(
            'qty_break',
            data_get($response->json(), 'data.items.0.pricing_rule.kind')
        );
    }

    public function test_bundle_price_preview_returns_applied_group(): void
    {
        $cashier = $this->createUserWithPermissions([
            'transactions-access',
            'cashier-shifts-access',
            'cashier-shifts-open',
            'cashier-shifts-close',
        ]);
        $this->openShiftFor($cashier);
        $productA = $this->createProduct('Produk Bundle A');
        $productB = $this->createProduct('Produk Bundle B');

        Cart::create([
            'cashier_id' => $cashier->id,
            'product_id' => $productA->id,
            'qty' => 1,
            'price' => $productA->sell_price,
        ]);
        Cart::create([
            'cashier_id' => $cashier->id,
            'product_id' => $productB->id,
            'qty' => 1,
            'price' => $productB->sell_price,
        ]);

        $rule = PricingRule::create([
            'name' => 'Bundle Hemat',
            'kind' => PricingRule::KIND_BUNDLE_PRICE,
            'is_active' => true,
            'priority' => 400,
            'target_type' => 'all',
            'customer_scope' => 'all',
            'discount_type' => 'fixed_price',
            'discount_value' => 100000,
        ]);
        $rule->bundleItems()->createMany([
            ['product_id' => $productA->id, 'quantity' => 1, 'sort_order' => 0],
            ['product_id' => $productB->id, 'quantity' => 1, 'sort_order' => 1],
        ]);

        $response = $this
            ->actingAs($cashier)
            ->postJson(route('transactions.pricing-preview'), []);

        $response->assertOk();
        $this->assertSame(
            20000,
            data_get($response->json(), 'data.summary.promo_discount_total')
        );
        $this->assertCount(1, data_get($response->json(), 'data.applied_groups', []));
    }

    public function test_buy_x_get_y_preview_discounts_reward_item(): void
    {
        $cashier = $this->createUserWithPermissions([
            'transactions-access',
            'cashier-shifts-access',
            'cashier-shifts-open',
            'cashier-shifts-close',
        ]);
        $this->openShiftFor($cashier);
        $buyProduct = $this->createProduct('Produk Buy');
        $getProduct = $this->createProduct('Produk Get');

        Cart::create([
            'cashier_id' => $cashier->id,
            'product_id' => $buyProduct->id,
            'qty' => 1,
            'price' => $buyProduct->sell_price,
        ]);
        Cart::create([
            'cashier_id' => $cashier->id,
            'product_id' => $getProduct->id,
            'qty' => 1,
            'price' => $getProduct->sell_price,
        ]);

        $rule = PricingRule::create([
            'name' => 'Buy 1 Get 1',
            'kind' => PricingRule::KIND_BUY_X_GET_Y,
            'is_active' => true,
            'priority' => 450,
            'target_type' => 'all',
            'customer_scope' => 'all',
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
        ]);
        $rule->buyGetItems()->createMany([
            ['product_id' => $buyProduct->id, 'role' => 'buy', 'quantity' => 1, 'sort_order' => 0],
            ['product_id' => $getProduct->id, 'role' => 'get', 'quantity' => 1, 'sort_order' => 1],
        ]);

        $response = $this
            ->actingAs($cashier)
            ->postJson(route('transactions.pricing-preview'), []);

        $response->assertOk();
        $this->assertSame(
            60000,
            data_get($response->json(), 'data.summary.promo_discount_total')
        );
        $this->assertSame(
            'buy_x_get_y',
            data_get($response->json(), 'data.items.1.pricing_rule.kind')
        );
    }

    public function test_transaction_checkout_recalculates_grand_total_using_pricing_rules(): void
    {
        $cashier = $this->createUserWithPermissions([
            'transactions-access',
            'cashier-shifts-access',
            'cashier-shifts-open',
            'cashier-shifts-close',
        ]);
        $shift = $this->openShiftFor($cashier);
        $product = $this->createProduct();
        $customer = Customer::create([
            'name' => 'Customer Promo',
            'no_telp' => '628777888999',
            'address' => 'Jl. Promo No. 1',
        ]);

        Cart::create([
            'cashier_id' => $cashier->id,
            'product_id' => $product->id,
            'qty' => 2,
            'price' => $product->sell_price * 2,
        ]);

        PricingRule::create([
            'name' => 'Harga Spesial Produk',
            'is_active' => true,
            'priority' => 300,
            'target_type' => 'product',
            'product_id' => $product->id,
            'customer_scope' => 'all',
            'discount_type' => 'fixed_price',
            'discount_value' => 50000,
        ]);

        $response = $this
            ->actingAs($cashier)
            ->post(route('transactions.store'), [
                'customer_id' => $customer->id,
                'discount' => 5000,
                'shipping_cost' => 0,
                'grand_total' => 999999,
                'cash' => 100000,
                'change' => 0,
            ]);

        $transaction = Transaction::with(['details', 'profits'])->latest('id')->first();

        $response->assertRedirect(route('transactions.print', $transaction->invoice));
        $this->assertNotNull($transaction);
        $this->assertSame($shift->id, $transaction->cashier_shift_id);
        $this->assertSame(95000, (int) $transaction->grand_total);
        $this->assertSame(5000, (int) $transaction->discount);
        $this->assertSame(100000, (int) $transaction->cash);
        $this->assertSame(5000, (int) $transaction->change);

        $detail = $transaction->details->first();
        $this->assertSame(60000, (int) $detail->base_unit_price);
        $this->assertSame(50000, (int) $detail->unit_price);
        $this->assertSame(100000, (int) $detail->price);
        $this->assertSame(20000, (int) $detail->discount_total);
        $this->assertSame('Harga Spesial Produk', $detail->pricing_rule_name);

        $profit = $transaction->profits->first();
        $this->assertSame(5000, (int) $profit->total);
        $this->assertDatabaseMissing('carts', [
            'cashier_id' => $cashier->id,
        ]);
        $this->assertSame(23, $product->fresh()->stock);
    }

    private function createUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function openShiftFor(User $cashier)
    {
        return \App\Models\CashierShift::create([
            'user_id' => $cashier->id,
            'opened_by' => $cashier->id,
            'opened_at' => now(),
            'opening_cash' => 100000,
            'expected_cash' => 100000,
            'status' => 'open',
        ]);
    }

    private function createProduct(?string $title = null): Product
    {
        $category = Category::create([
            'name' => 'Snack Promo '.Str::upper(Str::random(4)),
            'description' => 'Kategori promo',
            'image' => 'category.png',
        ]);

        return Product::create([
            'category_id' => $category->id,
            'image' => 'product.png',
            'barcode' => 'BRCD-'.Str::upper(Str::random(10)),
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'title' => $title ?? 'Produk Promo',
            'description' => 'Produk untuk pengujian promo.',
            'buy_price' => 45000,
            'sell_price' => 60000,
            'stock' => 25,
        ]);
    }
}
