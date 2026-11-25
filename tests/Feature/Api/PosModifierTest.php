<?php

namespace Tests\Feature\Api;

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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosModifierTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Product $product;

    private Modifier $large;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        $this->cashier = User::where('email', 'cashier@gmail.com')->firstOrFail();
        $category = Category::create(['name' => 'Minuman', 'image' => 'cat.png', 'description' => 'Minuman']);

        $this->product = Product::create([
            'title' => 'Kopi Susu',
            'sku' => 'KOPI-API',
            'barcode' => null,
            'buy_price' => 5000,
            'sell_price' => 18000,
            'stock' => 0,
            'track_stock' => false,
            'category_id' => $category->id,
            'tax_rate' => 0,
        ]);

        $warehouse = Warehouse::create([
            'code' => 'PUSAT', 'name' => 'Pusat', 'type' => 'main', 'is_active' => true, 'sort_order' => 0,
        ]);

        $group = ModifierGroup::create([
            'name' => 'Ukuran', 'selection_type' => 'single', 'min_select' => 1, 'max_select' => 1,
        ]);
        $this->large = Modifier::create([
            'modifier_group_id' => $group->id, 'name' => 'Large', 'price_delta' => 3000,
        ]);
        $this->product->modifierGroups()->attach($group->id, ['sort_order' => 0, 'is_active' => true]);

        app(CashierShiftService::class)->openShift($this->cashier, $this->cashier, 0, null, $warehouse->id);

        Sanctum::actingAs($this->cashier, ['pos-access']);
    }

    public function test_product_list_exposes_modifier_groups_and_non_stock_items(): void
    {
        $response = $this->getJson('/api/v1/pos/products')->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $this->product->id);

        $this->assertNotNull($row);
        $this->assertFalse($row['track_stock']);
        $this->assertSame(1, count($row['modifier_groups']));
        $this->assertSame(3000, $row['modifier_groups'][0]['options'][0]['price_delta']);
    }

    public function test_add_and_update_cart_preserve_modifiers(): void
    {
        $response = $this->postJson('/api/v1/pos/cart', [
            'product_id' => $this->product->id,
            'qty' => 1,
            'modifier_ids' => [$this->large->id],
            'note' => 'Less sugar',
        ])->assertOk();

        $cartId = $response->json('data.id');
        $this->assertSame(21000, (int) $response->json('data.price'));
        $this->assertSame('Less sugar', $response->json('data.note'));
        $this->assertSame(3000, (int) $response->json('data.modifiers.0.price_delta'));

        $this->putJson('/api/v1/pos/cart/'.$cartId, ['qty' => 2])
            ->assertOk()
            ->assertJsonPath('data.price', 42000);
    }

    public function test_missing_required_modifier_returns_422(): void
    {
        $this->postJson('/api/v1/pos/cart', [
            'product_id' => $this->product->id,
            'qty' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('modifier_ids');
    }
}
