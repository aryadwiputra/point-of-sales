<?php

namespace Tests\Feature\Fnb;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use App\Services\CheckoutService;
use App\Support\Checkout\CheckoutContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutModifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_copies_snapshot_note_and_skips_stock_for_non_stock_item(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        $cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
        $cashier->markEmailAsVerified();
        $this->actingAs($cashier);

        $category = Category::create(['name' => 'Minuman', 'image' => 'cat.png', 'description' => 'Minuman']);
        $warehouse = Warehouse::create(['code' => 'PUSAT', 'name' => 'Pusat', 'type' => 'main', 'is_active' => true, 'sort_order' => 0]);

        $product = Product::create([
            'title' => 'Kopi Susu', 'sku' => 'CHK-KOPI', 'barcode' => null,
            'buy_price' => 5000, 'sell_price' => 18000, 'stock' => 0,
            'track_stock' => false, 'category_id' => $category->id, 'tax_rate' => 0,
        ]);
        $warehouse->products()->attach($product->id, ['stock' => 0]);

        $group = ModifierGroup::create(['name' => 'Ukuran', 'selection_type' => 'single', 'min_select' => 1, 'max_select' => 1]);
        $large = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Large', 'price_delta' => 3000]);
        $product->modifierGroups()->attach($group->id, ['sort_order' => 0, 'is_active' => true]);

        app(CashierShiftService::class)->openShift($cashier, $cashier, 0, null, $warehouse->id);

        $this->post(route('transactions.addToCart'), [
            'product_id' => $product->id,
            'qty' => 1,
            'modifier_ids' => [$large->id],
            'note' => 'Less sugar',
        ]);

        $this->assertSame(21000, (int) Cart::firstOrFail()->price);

        $result = app(CheckoutService::class)->execute(new CheckoutContext(
            userId: $cashier->id,
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
            cashAmount: 21000,
            paymentGateway: null,
            useTenders: false,
            tenderInput: [],
            outlet: null,
            bankAccountId: null,
        ));

        $detail = $result->transaction->details()->with('modifiers')->firstOrFail();

        $this->assertSame(21000, (int) $detail->price);
        $this->assertSame('Less sugar', $detail->note);
        $this->assertSame(1, $detail->modifiers->count());
        $this->assertSame(3000, (int) $detail->modifiers->first()->price_delta);
        $this->assertDatabaseCount('stock_mutations', 0);
        $this->assertSame(0, (int) $warehouse->products()->where('product_id', $product->id)->first()->pivot->stock);
    }
}
