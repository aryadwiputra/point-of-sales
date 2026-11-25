<?php

namespace Tests\Feature\Fnb;

use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductModifierAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Category $category;

    protected ModifierGroup $group;

    protected Modifier $large;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        $this->admin = User::where('email', 'arya@gmail.com')->firstOrFail();
        $this->admin->markEmailAsVerified();

        $this->category = Category::create([
            'name' => 'Minuman',
            'image' => 'categories/drink.jpg',
            'description' => null,
        ]);

        $this->group = ModifierGroup::create([
            'name' => 'Ukuran',
            'selection_type' => 'single',
            'min_select' => 1,
            'max_select' => 1,
        ]);

        $this->large = Modifier::create([
            'modifier_group_id' => $this->group->id,
            'name' => 'Large',
            'price_delta' => 5000,
        ]);

        $this->actingAs($this->admin);
    }

    public function test_product_can_be_created_without_barcode_with_modifiers(): void
    {
        $this->post(route('products.store'), [
            'barcode' => '',
            'sku' => 'KOPI-ADM-'.uniqid(),
            'title' => 'Kopi Susu',
            'category_id' => $this->category->id,
            'buy_price' => 5000,
            'sell_price' => 18000,
            'stock' => 0,
            'track_stock' => false,
            'tax_rate' => 0,
            'modifier_groups' => [$this->group->id],
            'modifier_overrides' => [$this->large->id => 9000],
            'units' => [],
        ])->assertRedirect(route('products.index'));

        $product = Product::where('title', 'Kopi Susu')->firstOrFail();

        $this->assertNull($product->barcode);
        $this->assertFalse($product->tracksStock());
        $this->assertDatabaseHas('product_modifier_groups', [
            'product_id' => $product->id,
            'modifier_group_id' => $this->group->id,
        ]);
        $this->assertDatabaseHas('product_modifier_overrides', [
            'product_id' => $product->id,
            'modifier_id' => $this->large->id,
            'price_delta' => 9000,
        ]);
    }

    public function test_product_update_keeps_null_barcode_and_syncs_modifiers(): void
    {
        $product = Product::create([
            'title' => 'Teh',
            'sku' => 'TEH-ADM-'.uniqid(),
            'barcode' => null,
            'buy_price' => 3000,
            'sell_price' => 10000,
            'stock' => 0,
            'track_stock' => false,
            'tax_rate' => 0,
            'category_id' => $this->category->id,
        ]);

        $this->patch(route('products.update', $product->id), [
            'barcode' => '',
            'sku' => $product->sku,
            'title' => 'Teh Manis',
            'category_id' => $this->category->id,
            'buy_price' => 3000,
            'sell_price' => 12000,
            'min_stock' => 0,
            'max_stock' => 0,
            'track_stock' => false,
            'tax_rate' => 0,
            'modifier_groups' => [$this->group->id],
            'modifier_overrides' => [$this->large->id => 2000],
            'units' => [],
        ])->assertRedirect(route('products.index'));

        $product->refresh();

        $this->assertNull($product->barcode);
        $this->assertSame('Teh Manis', $product->title);
        $this->assertDatabaseHas('product_modifier_overrides', [
            'product_id' => $product->id,
            'modifier_id' => $this->large->id,
            'price_delta' => 2000,
        ]);
    }

    public function test_duplicate_barcode_is_still_rejected(): void
    {
        Product::create([
            'title' => 'Produk Lama',
            'sku' => 'OLD-'.uniqid(),
            'barcode' => 'BC-DUP-1',
            'buy_price' => 1000,
            'sell_price' => 2000,
            'stock' => 0,
            'tax_rate' => 0,
            'category_id' => $this->category->id,
        ]);

        $this->post(route('products.store'), [
            'barcode' => 'BC-DUP-1',
            'sku' => 'NEW-'.uniqid(),
            'title' => 'Produk Baru',
            'category_id' => $this->category->id,
            'buy_price' => 1000,
            'sell_price' => 2000,
            'stock' => 0,
            'tax_rate' => 0,
            'units' => [],
        ])->assertSessionHasErrors('barcode');
    }
}
