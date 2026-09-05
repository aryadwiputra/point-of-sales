<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->category = Category::create([
            'name' => 'Kategori Test',
            'image' => '',
            'description' => '',
        ]);
    }

    private function makeProduct(string $title, string $barcode): Product
    {
        return Product::create([
            'title' => $title,
            'barcode' => $barcode,
            'sku' => 'SKU-'.$barcode,
            'image' => '',
            'description' => '',
            'buy_price' => 10000,
            'sell_price' => 15000,
            'stock' => 10,
            'category_id' => $this->category->id,
            'tax_type' => 'exclusive',
            'tax_rate' => 0,
            'min_stock' => 0,
            'max_stock' => 100,
            'is_composite' => false,
        ]);
    }

    public function test_products_requires_auth(): void
    {
        $this->getJson('/api/v1/products')->assertStatus(401);
    }

    public function test_products_index_paginates(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        for ($i = 1; $i <= 25; $i++) {
            $this->makeProduct('Produk '.$i, 'BC-'.$i);
        }

        $response = $this->getJson('/api/v1/products?per_page=10');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonCount(10, 'data');
    }

    public function test_products_search_filters(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->makeProduct('Minyak Goreng', 'BC-MG');
        $this->makeProduct('Gula Pasir', 'BC-GP');
        $this->makeProduct('Kopi', 'BC-KP');

        $this->getJson('/api/v1/products?search=goreng')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Minyak Goreng');
    }

    public function test_products_store_creates(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/v1/products', [
            'title' => 'Produk Baru',
            'barcode' => 'NEW-001',
            'buy_price' => 5000,
            'sell_price' => 7500,
            'category_id' => $this->category->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Produk Baru');

        $this->assertDatabaseHas('products', ['barcode' => 'NEW-001']);
    }

    public function test_products_store_validation_duplicate_barcode(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->makeProduct('Produk Ada', 'DUP-001');

        $this->postJson('/api/v1/products', [
            'title' => 'Dup',
            'barcode' => 'DUP-001',
            'buy_price' => 1,
            'sell_price' => 2,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('barcode');
    }

    public function test_products_update_partial(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $product = $this->makeProduct('Produk Update', 'UPD-001');

        $this->putJson("/api/v1/products/{$product->id}", ['sell_price' => 20000])
            ->assertOk()
            ->assertJsonPath('data.sell_price', 20000);
    }

    public function test_products_show_404_for_missing(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->getJson('/api/v1/products/99999')->assertStatus(404);
    }

    public function test_products_destroy_returns_204(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $product = $this->makeProduct('Produk Hapus', 'DEL-001');

        $this->deleteJson("/api/v1/products/{$product->id}")->assertStatus(204);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
