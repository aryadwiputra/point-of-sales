<?php

namespace Tests\Feature\Loyalty;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerVoucher;
use App\Models\LoyaltyPointHistory;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LoyaltyFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
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

    public function test_loyalty_preview_returns_voucher_and_points_discount(): void
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
            'name' => 'Member Preview',
            'no_telp' => '628777000111',
            'address' => 'Jl. Preview',
            'is_loyalty_member' => true,
            'member_code' => 'MEM-PREVIEW',
            'loyalty_tier' => LoyaltyService::TIER_GOLD,
            'loyalty_points' => 50,
            'loyalty_member_since' => now()->subMonth(),
        ]);
        $voucher = CustomerVoucher::create([
            'customer_id' => $customer->id,
            'code' => 'VCR-PREVIEW',
            'name' => 'Voucher Preview',
            'discount_type' => CustomerVoucher::TYPE_FIXED_AMOUNT,
            'discount_value' => 10000,
            'minimum_order' => 50000,
            'is_active' => true,
        ]);

        Cart::create([
            'cashier_id' => $cashier->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => $product->sell_price,
        ]);

        $response = $this
            ->actingAs($cashier)
            ->postJson(route('transactions.pricing-preview'), [
                'customer_id' => $customer->id,
                'redeem_points' => 10,
                'customer_voucher_id' => $voucher->id,
            ]);

        $response->assertOk();
        $this->assertSame(
            10000,
            data_get($response->json(), 'data.summary.voucher_discount_total')
        );
        $this->assertSame(
            1000,
            data_get($response->json(), 'data.summary.loyalty_discount_total')
        );
        $this->assertSame(
            49000,
            data_get($response->json(), 'data.summary.grand_total')
        );
    }

    public function test_checkout_redeems_points_uses_voucher_and_earns_new_points(): void
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
            'name' => 'Member Checkout',
            'no_telp' => '628777000222',
            'address' => 'Jl. Checkout',
            'is_loyalty_member' => true,
            'member_code' => 'MEM-CHECKOUT',
            'loyalty_tier' => LoyaltyService::TIER_SILVER,
            'loyalty_points' => 20,
            'loyalty_member_since' => now()->subMonths(2),
        ]);
        $voucher = CustomerVoucher::create([
            'customer_id' => $customer->id,
            'code' => 'VCR-CHECKOUT',
            'name' => 'Voucher Checkout',
            'discount_type' => CustomerVoucher::TYPE_FIXED_AMOUNT,
            'discount_value' => 10000,
            'minimum_order' => 50000,
            'is_active' => true,
        ]);

        Cart::create([
            'cashier_id' => $cashier->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => $product->sell_price,
        ]);

        $response = $this
            ->actingAs($cashier)
            ->post(route('transactions.store'), [
                'customer_id' => $customer->id,
                'discount' => 0,
                'redeem_points' => 10,
                'customer_voucher_id' => $voucher->id,
                'shipping_cost' => 0,
                'grand_total' => 999999,
                'cash' => 50000,
            ]);

        $transaction = Transaction::latest('id')->first();

        $response->assertRedirect(route('transactions.print', $transaction->invoice));
        $this->assertSame(49000, (int) $transaction->grand_total);
        $this->assertSame(10, (int) $transaction->loyalty_points_redeemed);
        $this->assertSame(1000, (int) $transaction->loyalty_discount_total);
        $this->assertSame(10000, (int) $transaction->customer_voucher_discount);
        $this->assertSame('VCR-CHECKOUT', $transaction->customer_voucher_code);
        $this->assertSame(4, (int) $transaction->loyalty_points_earned);

        $customer->refresh();
        $voucher->refresh();

        $this->assertSame(14, (int) $customer->loyalty_points);
        $this->assertTrue($voucher->is_used);
        $this->assertSame($transaction->id, $voucher->used_transaction_id);
        $this->assertDatabaseCount('loyalty_point_histories', 3);
        $this->assertSame(
            [
                LoyaltyPointHistory::TYPE_REDEEM,
                LoyaltyPointHistory::TYPE_VOUCHER,
                LoyaltyPointHistory::TYPE_EARN,
            ],
            LoyaltyPointHistory::query()->orderBy('id')->pluck('type')->all()
        );
    }

    public function test_loyalty_settings_can_disable_redeem_and_recalculate_tier(): void
    {
        Setting::set('loyalty_enable_redeem', '0');
        Setting::set('loyalty_tier_silver_threshold', '100000');
        Setting::set('loyalty_tier_gold_threshold', '200000');
        Setting::set('loyalty_tier_platinum_threshold', '300000');

        $cashier = $this->createUserWithPermissions([
            'transactions-access',
            'cashier-shifts-access',
            'cashier-shifts-open',
            'cashier-shifts-close',
        ]);
        $this->openShiftFor($cashier);
        $product = $this->createProduct();
        $customer = Customer::create([
            'name' => 'Member Settings',
            'no_telp' => '628777000333',
            'address' => 'Jl. Settings',
            'is_loyalty_member' => true,
            'member_code' => 'MEM-SETTINGS',
            'loyalty_tier' => LoyaltyService::TIER_REGULAR,
            'loyalty_points' => 50,
            'loyalty_member_since' => now()->subMonths(2),
        ]);

        Cart::create([
            'cashier_id' => $cashier->id,
            'product_id' => $product->id,
            'qty' => 4,
            'price' => $product->sell_price * 4,
        ]);

        $previewResponse = $this
            ->actingAs($cashier)
            ->postJson(route('transactions.pricing-preview'), [
                'customer_id' => $customer->id,
                'redeem_points' => 10,
            ]);

        $previewResponse->assertOk();
        $this->assertSame(
            0,
            data_get($previewResponse->json(), 'data.summary.loyalty_discount_total')
        );

        $this
            ->actingAs($cashier)
            ->post(route('transactions.store'), [
                'customer_id' => $customer->id,
                'discount' => 0,
                'redeem_points' => 10,
                'shipping_cost' => 0,
                'grand_total' => 999999,
                'cash' => 300000,
            ]);

        $customer->refresh();

        $this->assertSame(74, (int) $customer->loyalty_points);
        $this->assertSame(LoyaltyService::TIER_GOLD, $customer->loyalty_tier);
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

    private function createProduct(): Product
    {
        $category = Category::create([
            'name' => 'Loyalty Category',
            'description' => 'Kategori loyalty',
            'image' => 'category.png',
        ]);

        return Product::create([
            'category_id' => $category->id,
            'image' => 'product.png',
            'barcode' => 'BRCD-'.Str::upper(Str::random(10)),
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'title' => 'Produk Loyalty',
            'description' => 'Produk untuk pengujian loyalty.',
            'buy_price' => 40000,
            'sell_price' => 60000,
            'stock' => 25,
            'tax_rate' => 0,
        ]);
    }
}
