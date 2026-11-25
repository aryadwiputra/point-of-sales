<?php

namespace Tests\Feature\Fnb;

use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductModifierOverride;
use App\Services\ModifierService;
use App\Support\CartLineSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ModifierServiceTest extends TestCase
{
    use RefreshDatabase;

    private ModifierService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ModifierService::class);
    }

    private function product(array $attributes = []): Product
    {
        static $seq = 0;
        $seq++;

        return Product::create(array_merge([
            'category_id' => Category::firstOrCreate(['name' => 'Minuman'])->id,
            'title' => 'Kopi Susu',
            'sku' => 'KOPI-'.$seq,
            'barcode' => null,
            'buy_price' => 5000,
            'sell_price' => 18000,
            'stock' => 0,
            'track_stock' => false,
            'tax_rate' => 0,
        ], $attributes));
    }

    public function test_effective_delta_prefers_product_override(): void
    {
        $product = $this->product();
        $group = ModifierGroup::create(['name' => 'Ukuran', 'selection_type' => 'single', 'min_select' => 1, 'max_select' => 1]);
        $large = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Large', 'price_delta' => 5000]);
        $product->modifierGroups()->attach($group->id, ['sort_order' => 0, 'is_active' => true]);
        ProductModifierOverride::create(['product_id' => $product->id, 'modifier_id' => $large->id, 'price_delta' => 7000, 'is_active' => true]);

        $snapshot = $this->service->selectionSnapshot($product->fresh(), [$large->id]);

        $this->assertSame(7000, $snapshot[0]['price_delta']);
        $this->assertSame(7000, $this->service->deltaFor($snapshot));
    }

    public function test_missing_required_group_is_rejected(): void
    {
        $product = $this->product();
        $group = ModifierGroup::create(['name' => 'Gula', 'selection_type' => 'single', 'min_select' => 1, 'max_select' => 1]);
        Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Normal']);
        $product->modifierGroups()->attach($group->id, ['sort_order' => 0, 'is_active' => true]);

        $this->expectException(ValidationException::class);
        $this->service->selectionSnapshot($product->fresh(), []);
    }

    public function test_option_from_another_product_is_rejected(): void
    {
        $product = $this->product();
        $group = ModifierGroup::create(['name' => 'Topping', 'selection_type' => 'multiple', 'min_select' => 0, 'max_select' => 3]);
        $boba = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Boba', 'price_delta' => 3000]);
        $other = $this->product(['title' => 'Teh']);
        $other->modifierGroups()->attach($group->id, ['sort_order' => 0, 'is_active' => true]);

        $this->expectException(ValidationException::class);
        $this->service->selectionSnapshot($product->fresh(), [$boba->id]);
    }

    public function test_single_group_rejects_two_options_and_max_select_is_enforced(): void
    {
        $product = $this->product();
        $group = ModifierGroup::create(['name' => 'Ukuran', 'selection_type' => 'single', 'min_select' => 1, 'max_select' => 1]);
        $a = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Regular']);
        $b = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Large', 'price_delta' => 5000]);
        $product->modifierGroups()->attach($group->id, ['sort_order' => 0, 'is_active' => true]);

        $this->expectException(ValidationException::class);
        $this->service->selectionSnapshot($product->fresh(), [$a->id, $b->id]);
    }

    public function test_inactive_override_disables_option_for_that_product(): void
    {
        $product = $this->product();
        $group = ModifierGroup::create(['name' => 'Susu', 'selection_type' => 'multiple', 'min_select' => 0, 'max_select' => 2]);
        $oat = Modifier::create(['modifier_group_id' => $group->id, 'name' => 'Oat']);
        $product->modifierGroups()->attach($group->id, ['sort_order' => 0, 'is_active' => true]);
        ProductModifierOverride::create(['product_id' => $product->id, 'modifier_id' => $oat->id, 'price_delta' => 6000, 'is_active' => false]);

        $this->expectException(ValidationException::class);
        $this->service->selectionSnapshot($product->fresh(), [$oat->id]);
    }

    public function test_signature_is_order_independent_and_note_normalized(): void
    {
        $a = CartLineSignature::make([7, 3], '  Less   sugar ');
        $b = CartLineSignature::make([3, 7, 3], 'Less sugar');

        $this->assertSame($a, $b);
        $this->assertNotSame($a, CartLineSignature::make([7, 3], 'Less ice'));
        $this->assertSame(40, strlen($a));
    }
}
