<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductDisplayModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'dashboard-access',
            'products-create',
            'products-edit',
            'products-delete',
            'categories-create',
            'categories-edit',
            'categories-delete',
        ] as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_default_product_display_mode_is_image_grid(): void
    {
        $user = $this->createUserWithPermissions(['dashboard-access']);

        $this->actingAs($user)
            ->get(route('settings.store'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.product_display_mode', Setting::PRODUCT_DISPLAY_IMAGE_GRID)
                ->where('appSettings.product_display_mode', Setting::PRODUCT_DISPLAY_IMAGE_GRID));
    }

    public function test_store_settings_can_update_product_display_mode(): void
    {
        $user = $this->createUserWithPermissions(['dashboard-access']);

        $this->actingAs($user)
            ->post(route('settings.store.update'), $this->storeSettingsPayload([
                'product_display_mode' => Setting::PRODUCT_DISPLAY_COMPACT_LIST,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('settings', [
            'key' => 'product_display_mode',
            'value' => Setting::PRODUCT_DISPLAY_COMPACT_LIST,
        ]);

        $this->actingAs($user)
            ->get(route('settings.store'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.product_display_mode', Setting::PRODUCT_DISPLAY_COMPACT_LIST)
                ->where('appSettings.product_display_mode', Setting::PRODUCT_DISPLAY_COMPACT_LIST));
    }

    public function test_store_settings_reject_invalid_product_display_mode(): void
    {
        $user = $this->createUserWithPermissions(['dashboard-access']);

        $this->actingAs($user)
            ->post(route('settings.store.update'), $this->storeSettingsPayload([
                'product_display_mode' => 'gallery_wall',
            ]))
            ->assertSessionHasErrors('product_display_mode');
    }

    public function test_compact_list_allows_product_without_image(): void
    {
        Setting::set('product_display_mode', Setting::PRODUCT_DISPLAY_COMPACT_LIST);
        $user = $this->createUserWithPermissions(['products-create']);
        $category = $this->createCategory();

        $this->actingAs($user)
            ->post(route('products.store'), $this->productPayload($category))
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'title' => 'Produk Tanpa Foto',
            'image' => null,
        ]);
    }

    public function test_image_grid_requires_product_image(): void
    {
        Setting::set('product_display_mode', Setting::PRODUCT_DISPLAY_IMAGE_GRID);
        $user = $this->createUserWithPermissions(['products-create']);
        $category = $this->createCategory();

        $this->actingAs($user)
            ->post(route('products.store'), $this->productPayload($category))
            ->assertSessionHasErrors('image');
    }

    public function test_product_without_image_can_be_updated_and_deleted(): void
    {
        Setting::set('product_display_mode', Setting::PRODUCT_DISPLAY_COMPACT_LIST);
        $user = $this->createUserWithPermissions(['products-edit', 'products-delete']);
        $category = $this->createCategory();
        $product = $this->createProductWithoutImage($category);

        $this->actingAs($user)
            ->put(route('products.update', $product), $this->productPayload($category, [
                'sku' => $product->sku,
                'title' => 'Produk Tanpa Foto Updated',
                'product_units' => [
                    [
                        'id' => $product->baseUnit->id,
                        'label' => 'pcs',
                        'conversion_qty' => '1',
                        'is_base_unit' => true,
                        'buy_price' => 11000,
                        'sell_price' => 16000,
                        'barcode' => $product->barcode,
                    ],
                ],
            ]))
            ->assertRedirect(route('products.index'));

        $this->actingAs($user)
            ->delete(route('products.destroy', $product->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    public function test_compact_list_allows_category_without_image(): void
    {
        Setting::set('product_display_mode', Setting::PRODUCT_DISPLAY_COMPACT_LIST);
        $user = $this->createUserWithPermissions(['categories-create']);

        $this->actingAs($user)
            ->post(route('categories.store'), [
                'name' => 'ATK',
                'description' => 'Peralatan kantor',
            ])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'ATK',
            'image' => null,
        ]);
    }

    public function test_image_grid_requires_category_image(): void
    {
        Setting::set('product_display_mode', Setting::PRODUCT_DISPLAY_IMAGE_GRID);
        $user = $this->createUserWithPermissions(['categories-create']);

        $this->actingAs($user)
            ->post(route('categories.store'), [
                'name' => 'ATK',
                'description' => 'Peralatan kantor',
            ])
            ->assertSessionHasErrors('image');
    }

    public function test_category_without_image_can_be_updated_and_deleted(): void
    {
        Setting::set('product_display_mode', Setting::PRODUCT_DISPLAY_COMPACT_LIST);
        $user = $this->createUserWithPermissions(['categories-edit', 'categories-delete']);
        $category = $this->createCategory(['image' => null]);

        $this->actingAs($user)
            ->put(route('categories.update', $category), [
                'name' => 'ATK Updated',
                'description' => 'Peralatan kantor updated',
            ])
            ->assertRedirect(route('categories.index'));

        $this->actingAs($user)
            ->delete(route('categories.destroy', $category->id))
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_image_grid_still_allows_product_and_category_images(): void
    {
        Storage::fake('local');
        Setting::set('product_display_mode', Setting::PRODUCT_DISPLAY_IMAGE_GRID);
        $user = $this->createUserWithPermissions(['products-create', 'categories-create']);

        $this->actingAs($user)
            ->post(route('categories.store'), [
                'name' => 'Foto Category',
                'description' => 'Kategori dengan foto',
                'image' => UploadedFile::fake()->image('category.jpg'),
            ])
            ->assertRedirect(route('categories.index'));

        $category = Category::query()->where('name', 'Foto Category')->firstOrFail();

        $this->actingAs($user)
            ->post(route('products.store'), [
                ...$this->productPayload($category),
                'image' => UploadedFile::fake()->image('product.jpg'),
            ])
            ->assertRedirect(route('products.index'));

        $this->assertNotNull(Product::query()->where('title', 'Produk Tanpa Foto')->first()?->getRawOriginal('image'));
        $this->assertNotNull($category->fresh()->getRawOriginal('image'));
    }

    private function createUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function storeSettingsPayload(array $overrides = []): array
    {
        return [
            'store_name' => 'Toko ATK',
            'store_address' => 'Jl. Kertas No. 1',
            'store_phone' => '08123456789',
            'store_email' => 'atk@example.test',
            'store_website' => '',
            'store_city' => 'Jakarta',
            'product_display_mode' => Setting::PRODUCT_DISPLAY_IMAGE_GRID,
            ...$overrides,
        ];
    }

    private function productPayload(Category $category, array $overrides = []): array
    {
        $barcode = 'BRCD-'.Str::upper(Str::random(8));

        return [
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'title' => 'Produk Tanpa Foto',
            'description' => 'Produk compact list',
            'category_id' => $category->id,
            'stock' => 10,
            'product_units' => [
                [
                    'label' => 'pcs',
                    'conversion_qty' => '1',
                    'is_base_unit' => true,
                    'buy_price' => 10000,
                    'sell_price' => 15000,
                    'barcode' => $barcode,
                ],
            ],
            ...$overrides,
        ];
    }

    private function createCategory(array $overrides = []): Category
    {
        return Category::create([
            'name' => 'Kategori '.Str::upper(Str::random(4)),
            'description' => 'Kategori test',
            'image' => 'category.png',
            ...$overrides,
        ]);
    }

    private function createProductWithoutImage(Category $category): Product
    {
        $barcode = 'BRCD-'.Str::upper(Str::random(8));
        $product = Product::create([
            'category_id' => $category->id,
            'image' => null,
            'barcode' => $barcode,
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'title' => 'Produk Tanpa Foto',
            'description' => 'Produk compact list',
            'buy_price' => 10000,
            'sell_price' => 15000,
            'stock' => 10,
        ]);

        ProductUnit::create([
            'product_id' => $product->id,
            'label' => 'pcs',
            'conversion_qty' => 1,
            'is_base_unit' => true,
            'buy_price' => 10000,
            'sell_price' => 15000,
            'barcode' => $barcode,
        ]);

        return $product->fresh('baseUnit');
    }
}
