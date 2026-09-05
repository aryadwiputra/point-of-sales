<?php

namespace Tests\Feature\Products;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductUnitTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $cashier;

    protected Warehouse $pusat;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        $this->admin = User::where('email', 'arya@gmail.com')->first();
        $this->admin->markEmailAsVerified();
        $this->cashier = User::where('email', 'cashier@gmail.com')->first();
        $this->cashier->markEmailAsVerified();

        $this->pusat = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->category = Category::create([
            'name' => 'Satuan Uji',
            'description' => 'Kategori pengujian',
            'image' => 'category.png',
        ]);
    }

    protected function createProduct(array $overrides = []): Product
    {
        $product = Product::create(array_merge([
            'category_id' => $this->category->id,
            'image' => 'product.png',
            'barcode' => 'BRCD-'.Str::upper(Str::random(10)),
            'sku' => 'SKU-'.Str::upper(Str::random(10)),
            'title' => 'Produk Uji',
            'description' => 'Deskripsi uji.',
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 100,
            'tax_rate' => 0,
        ], $overrides));

        $this->pusat->products()->attach($product->id, ['stock' => $product->stock]);

        return $product;
    }

    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'image' => UploadedFile::fake()->image('product.png'),
            'barcode' => 'BRCD-'.Str::upper(Str::random(10)),
            'sku' => 'SKU-'.Str::upper(Str::random(10)),
            'title' => 'Produk Uji',
            'description' => 'Deskripsi uji.',
            'category_id' => $this->category->id,
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 100,
            'tax_rate' => 0,
        ], $overrides);
    }

    public function test_unit_crud_store_update_destroy(): void
    {
        $this->actingAs($this->admin)
            ->post(route('settings.units.store'), ['code' => 'LSN', 'name' => 'Lusin', 'symbol' => 'lsn'])
            ->assertRedirect();

        $unit = Unit::where('code', 'LSN')->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('settings.units.update', $unit), ['code' => 'LSN', 'name' => 'Lusin Besar', 'symbol' => 'LSN'])
            ->assertRedirect();

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'name' => 'Lusin Besar']);

        $this->actingAs($this->admin)
            ->delete(route('settings.units.destroy', $unit))
            ->assertRedirect();

        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
    }

    public function test_unit_cannot_be_deleted_while_in_use(): void
    {
        $unit = Unit::where('code', 'PCS')->firstOrFail();
        $product = $this->createProduct();
        $product->units()->attach($unit->id, [
            'is_base' => true,
            'conversion_factor' => 1,
            'buy_price' => 5000,
            'sell_price' => 10000,
        ]);

        $this->actingAs($this->admin)
            ->from(route('settings.units.index'))
            ->delete(route('settings.units.destroy', $unit))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('units', ['id' => $unit->id]);
    }

    public function test_store_product_with_units_pivot(): void
    {
        $pcs = Unit::where('code', 'PCS')->firstOrFail();
        $box = Unit::where('code', 'BOX')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('products.store'), $this->validPayload([
                'units' => [
                    ['unit_id' => $pcs->id, 'is_base' => true, 'conversion_factor' => 1, 'buy_price' => 5000, 'sell_price' => 10000],
                    ['unit_id' => $box->id, 'is_base' => false, 'conversion_factor' => 12, 'buy_price' => 55000, 'sell_price' => 115000, 'barcode' => 'BRCD-BOX'],
                ],
            ]))
            ->assertRedirect(route('products.index'));

        $product = Product::latest('id')->first();

        $this->assertDatabaseHas('product_units', [
            'product_id' => $product->id,
            'unit_id' => $pcs->id,
            'is_base' => true,
        ]);
        $this->assertDatabaseHas('product_units', [
            'product_id' => $product->id,
            'unit_id' => $box->id,
            'conversion_factor' => 12,
            'sell_price' => 115000,
            'barcode' => 'BRCD-BOX',
        ]);
    }

    public function test_store_without_units_creates_default_base_unit(): void
    {
        $pcs = Unit::where('code', 'PCS')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('products.store'), $this->validPayload())
            ->assertRedirect(route('products.index'));

        $product = Product::latest('id')->first();

        $this->assertDatabaseHas('product_units', [
            'product_id' => $product->id,
            'unit_id' => $pcs->id,
            'is_base' => true,
            'conversion_factor' => 1,
        ]);
    }

    public function test_web_pos_add_to_cart_with_unit_converts_to_base(): void
    {
        $pcs = Unit::where('code', 'PCS')->firstOrFail();
        $box = Unit::where('code', 'BOX')->firstOrFail();

        $product = $this->createProduct(['stock' => 24]);
        $product->units()->sync([
            $pcs->id => ['is_base' => true, 'conversion_factor' => 1, 'buy_price' => 5000, 'sell_price' => 10000],
            $box->id => ['is_base' => false, 'conversion_factor' => 12, 'buy_price' => 55000, 'sell_price' => 115000],
        ]);

        $this->seed([PermissionSeeder::class]);
        $this->actingAs($this->cashier);

        app(CashierShiftService::class)->openShift($this->cashier, $this->cashier, 0, null, $this->pusat->id);

        $this->post(route('transactions.addToCart'), [
            'product_id' => $product->id,
            'sell_price' => 115000,
            'qty' => 1,
            'unit_id' => $box->id,
        ])->assertRedirect();

        $cart = Cart::where('product_id', $product->id)->firstOrFail();
        // cart stores display qty + conversion factor; base qty = qty * factor
        $this->assertSame(1, (int) $cart->qty);
        $this->assertSame(12, (int) round($cart->qty * $cart->conversion_factor));
        $this->assertSame(115000, (int) $cart->price);

        // stock check: 2 box = 24 base units
        $this->post(route('transactions.addToCart'), [
            'product_id' => $product->id,
            'sell_price' => 115000,
            'qty' => 1,
            'unit_id' => $box->id,
        ])->assertRedirect();

        $cart = Cart::where('product_id', $product->id)->firstOrFail();
        $this->assertSame(2, (int) $cart->qty);
        $this->assertSame(24, (int) round($cart->qty * $cart->conversion_factor));
    }

    public function test_web_pos_add_to_cart_exceeding_base_stock_fails(): void
    {
        $pcs = Unit::where('code', 'PCS')->firstOrFail();
        $box = Unit::where('code', 'BOX')->firstOrFail();

        $product = $this->createProduct(['stock' => 10]);
        $product->units()->sync([
            $pcs->id => ['is_base' => true, 'conversion_factor' => 1, 'buy_price' => 5000, 'sell_price' => 10000],
            $box->id => ['is_base' => false, 'conversion_factor' => 12, 'buy_price' => 55000, 'sell_price' => 115000],
        ]);

        $this->actingAs($this->cashier);

        app(CashierShiftService::class)->openShift($this->cashier, $this->cashier, 0, null, $this->pusat->id);

        $this->from(route('transactions.index'))
            ->post(route('transactions.addToCart'), [
                'product_id' => $product->id,
                'sell_price' => 115000,
                'qty' => 1,
                'unit_id' => $box->id,
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('carts', ['product_id' => $product->id]);
    }
}
