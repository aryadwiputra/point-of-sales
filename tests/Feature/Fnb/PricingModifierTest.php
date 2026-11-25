<?php

namespace Tests\Feature\Fnb;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingModifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_includes_modifier_delta_in_base_price(): void
    {
        $category = Category::firstOrCreate(['name' => 'Minuman']);

        $product = Product::create([
            'title' => 'Kopi', 'sku' => 'PRICING-KOPI', 'barcode' => null,
            'buy_price' => 5000, 'sell_price' => 18000, 'stock' => 0,
            'track_stock' => false, 'category_id' => $category->id, 'tax_rate' => 0,
        ]);
        $warehouse = Warehouse::create([
            'code' => 'PUSAT', 'name' => 'Pusat', 'type' => 'main', 'is_active' => true, 'sort_order' => 0,
        ]);
        $group = ModifierGroup::create([
            'name' => 'Extra', 'selection_type' => 'multiple', 'min_select' => 0, 'max_select' => 5,
        ]);
        $shot = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Extra Shot', 'price_delta' => 5000]);

        $cart = Cart::create([
            'cashier_id' => User::factory()->create()->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id,
            'unit_id' => null, 'conversion_factor' => 1, 'qty' => 2, 'price' => 46000,
            'line_signature' => str_repeat('a', 40),
        ]);
        $cart->modifiers()->create([
            'modifier_id' => $shot->id, 'modifier_group_id' => $group->id,
            'group_name' => 'Extra', 'name' => 'Extra Shot', 'price_delta' => 5000,
        ]);

        $preview = app(PricingService::class)->previewCart(collect([$cart->fresh('product', 'modifiers')]));
        $item = $preview['items'][0];

        $this->assertSame(23000, $item['base_unit_price']);
        $this->assertSame(46000, $item['line_base_total']);
        $this->assertSame(46000, $item['line_total']);
    }
}
