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
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartModifierTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Warehouse $warehouse;

    private Product $product;

    private Modifier $large;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        $this->cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
        $this->cashier->markEmailAsVerified();

        $category = Category::create(['name' => 'Minuman', 'image' => 'cat.png', 'description' => 'Minuman']);

        $this->product = Product::create([
            'title' => 'Kopi Susu',
            'sku' => 'KOPI-CART',
            'barcode' => null,
            'buy_price' => 5000,
            'sell_price' => 18000,
            'stock' => 0,
            'track_stock' => false,
            'category_id' => $category->id,
            'tax_rate' => 0,
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'PUSAT', 'name' => 'Pusat', 'type' => 'main', 'is_active' => true, 'sort_order' => 0,
        ]);

        $group = ModifierGroup::create([
            'name' => 'Ukuran', 'selection_type' => 'single', 'min_select' => 1, 'max_select' => 1,
        ]);
        $this->large = Modifier::create([
            'modifier_group_id' => $group->id, 'name' => 'Large', 'price_delta' => 3000,
        ]);
        $this->product->modifierGroups()->attach($group->id, ['sort_order' => 0, 'is_active' => true]);

        app(CashierShiftService::class)->openShift($this->cashier, $this->cashier, 0, null, $this->warehouse->id);

        $this->actingAs($this->cashier);
    }

    public function test_required_group_blocks_add_without_selection(): void
    {
        $this->post(route('transactions.addToCart'), [
            'product_id' => $this->product->id,
            'qty' => 1,
        ])->assertSessionHasErrors('modifier_ids');

        $this->assertDatabaseCount('carts', 0);
    }

    public function test_add_stores_snapshot_and_modifier_price(): void
    {
        $this->post(route('transactions.addToCart'), [
            'product_id' => $this->product->id,
            'qty' => 2,
            'modifier_ids' => [$this->large->id],
            'note' => 'Less sugar',
        ])->assertRedirect();

        $cart = Cart::with('modifiers')->firstOrFail();

        $this->assertSame(42000, (int) $cart->price);
        $this->assertSame('Less sugar', $cart->note);
        $this->assertCount(1, $cart->modifiers);
        $this->assertSame(3000, (int) $cart->modifiers->first()->price_delta);
        $this->assertSame(40, strlen($cart->line_signature));
    }

    public function test_same_option_and_note_merges_but_different_note_does_not(): void
    {
        $base = ['product_id' => $this->product->id, 'qty' => 1, 'modifier_ids' => [$this->large->id], 'note' => 'Less sugar'];

        $this->post(route('transactions.addToCart'), $base);
        $this->post(route('transactions.addToCart'), $base);
        $this->post(route('transactions.addToCart'), [...$base, 'note' => 'No sugar']);

        $this->assertDatabaseCount('carts', 2);
        $this->assertSame(2, (int) Cart::where('note', 'Less sugar')->value('qty'));
    }

    public function test_update_quantity_preserves_modifier_delta(): void
    {
        $this->post(route('transactions.addToCart'), [
            'product_id' => $this->product->id,
            'qty' => 1,
            'modifier_ids' => [$this->large->id],
        ]);

        $cart = Cart::firstOrFail();

        $this->patch(route('transactions.updateCart', $cart->id), ['qty' => 3])->assertRedirect();

        $this->assertSame(63000, (int) $cart->fresh()->price);
    }
}
