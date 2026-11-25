<?php

namespace Tests\Feature\Fnb;

use App\Models\Category;
use App\Models\DineOrder;
use App\Models\DiningTable;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use App\Services\DineOrderService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DineInModifierTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected Warehouse $warehouse;

    protected Product $product;

    protected Modifier $large;

    protected DiningTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        $this->cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
        $this->cashier->markEmailAsVerified();

        $this->warehouse = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        app(CashierShiftService::class)->openShift(
            $this->cashier,
            $this->cashier,
            0,
            null,
            $this->warehouse->id,
        );

        $category = Category::create([
            'name' => 'Minuman',
            'image' => 'categories/drink.jpg',
            'description' => null,
        ]);

        $this->product = Product::create([
            'title' => 'Kopi Susu',
            'sku' => 'KOPI-DINE-'.uniqid(),
            'barcode' => null,
            'buy_price' => 5000,
            'sell_price' => 18000,
            'stock' => 100,
            'track_stock' => false,
            'tax_rate' => 0,
            'category_id' => $category->id,
        ]);
        $this->warehouse->products()->attach($this->product->id, ['stock' => 100]);

        $group = ModifierGroup::create([
            'name' => 'Ukuran',
            'selection_type' => 'single',
            'min_select' => 1,
            'max_select' => 1,
        ]);

        $this->large = Modifier::create([
            'modifier_group_id' => $group->id,
            'name' => 'Large',
            'price_delta' => 5000,
        ]);

        $this->product->modifierGroups()->attach($group->id, [
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->table = DiningTable::create([
            'name' => 'Meja 1',
            'token' => (string) Str::uuid(),
            'capacity' => 2,
            'pos_x' => 1,
            'pos_y' => 1,
            'shape' => 'square',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($this->cashier);
    }

    public function test_dine_order_prices_and_snapshots_modifiers_then_accepts_them(): void
    {
        $this->post(route('dine-order.store', $this->table->token), [
            'items' => [[
                'product_id' => $this->product->id,
                'qty' => 1,
                'note' => 'Less ice',
                'modifier_ids' => [$this->large->id],
            ]],
            'payment_option' => 'pay_at_counter',
        ])->assertRedirect();

        $order = DineOrder::with('items.modifiers')->firstOrFail();
        $item = $order->items->first();

        $this->assertSame(23000, (int) $item->price);
        $this->assertSame('Less ice', $item->note);
        $this->assertCount(1, $item->modifiers);
        $this->assertSame(5000, (int) $item->modifiers->first()->price_delta);

        app(DineOrderService::class)->accept($order);

        $this->assertDatabaseHas('transaction_details', [
            'product_id' => $this->product->id,
            'note' => 'Less ice',
            'unit_price' => 23000,
        ]);
        $this->assertDatabaseHas('transaction_detail_modifiers', [
            'group_name' => 'Ukuran',
            'name' => 'Large',
            'price_delta' => 5000,
        ]);

        $this->assertSame(100, (int) $this->product->fresh()->stock);
        $this->assertSame(100, (int) $this->product->fresh()->warehouses()->first()->pivot->stock);
    }

    public function test_missing_required_modifier_group_is_rejected(): void
    {
        $this->post(route('dine-order.store', $this->table->token), [
            'items' => [[
                'product_id' => $this->product->id,
                'qty' => 1,
                'modifier_ids' => [],
            ]],
            'payment_option' => 'pay_at_counter',
        ])->assertSessionHasErrors('modifier_ids');

        $this->assertDatabaseCount('dine_orders', 0);
    }
}
