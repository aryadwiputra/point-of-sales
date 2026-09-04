<?php

namespace Tests\Feature\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductTaxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
        ]);

        $this->admin = User::where('email', 'arya@gmail.com')->first();
        $this->admin->markEmailAsVerified();
        $this->actingAs($this->admin);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'image' => UploadedFile::fake()->image('product.jpg'),
            'barcode' => 'BC-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'title' => 'Produk Pajak',
            'description' => 'Deskripsi',
            'category_id' => Category::create([
                'name' => 'Kat '.uniqid(),
                'image' => 'categories/test.jpg',
                'description' => 'Deskripsi kategori',
            ])->id,
            'buy_price' => 10000,
            'sell_price' => 15000,
            'stock' => 10,
            'tax_type' => 'inclusive',
            'tax_rate' => '5.5',
        ], $overrides);
    }

    public function test_store_persists_tax_type_and_rate(): void
    {
        $response = $this->post(route('products.store'), $this->validPayload());

        $response->assertRedirect(route('products.index'));

        $product = Product::latest('id')->first();
        $this->assertEquals('inclusive', $product->tax_type);
        $this->assertEquals(5.5, $product->tax_rate);
    }

    public function test_store_defaults_tax_when_not_sent(): void
    {
        $payload = $this->validPayload();
        unset($payload['tax_type'], $payload['tax_rate']);

        $this->post(route('products.store'), $payload);

        $product = Product::latest('id')->first();
        $this->assertEquals('exclusive', $product->tax_type);
        $this->assertEquals(11.00, $product->tax_rate);
    }

    public function test_update_persists_tax_type_and_rate(): void
    {
        $product = Product::create([
            'image' => 'products/test.jpg',
            'barcode' => 'BC-1',
            'sku' => 'SKU-1',
            'title' => 'Produk Lama',
            'description' => 'Deskripsi',
            'category_id' => Category::create([
                'name' => 'Kat '.uniqid(),
                'image' => 'categories/test.jpg',
                'description' => 'Deskripsi kategori',
            ])->id,
            'buy_price' => 10000,
            'sell_price' => 15000,
            'stock' => 10,
            'tax_rate' => 0,
        ]);

        $payload = $this->validPayload([
            'tax_type' => 'exclusive',
            'tax_rate' => '0',
            '_method' => 'PUT',
        ]);

        $this->post(route('products.update', $product->id), $payload);

        $product->refresh();
        $this->assertEquals('exclusive', $product->tax_type);
        $this->assertEquals(0, $product->tax_rate);
    }

    public function test_store_rejects_invalid_tax_rate(): void
    {
        $this->post(route('products.store'), $this->validPayload([
            'tax_rate' => '150',
        ]))->assertSessionHasErrors('tax_rate');
    }
}
