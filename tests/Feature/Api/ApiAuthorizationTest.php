<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function actingWithAbilities(User $user, array $abilities): void
    {
        $token = $user->createToken('test', $abilities)->plainTextToken;
        $this->withToken($token);
    }

    public function test_token_without_master_data_ability_gets_403(): void
    {
        $this->actingWithAbilities($this->makeUser(), ['user:read']);

        $this->getJson('/api/v1/products')->assertStatus(403);
    }

    public function test_read_ability_cannot_write(): void
    {
        $this->actingWithAbilities($this->makeUser(), ['user:read', 'products-access']);

        $this->getJson('/api/v1/products')->assertOk();

        $this->postJson('/api/v1/products', [
            'title' => 'Produk Baru',
            'barcode' => 'AUTH-NEW-001',
            'buy_price' => 5000,
            'sell_price' => 7500,
        ])->assertStatus(403);
    }

    public function test_register_token_cannot_delete_master_data(): void
    {
        $this->actingWithAbilities($this->makeUser(), ['user:read']);

        $category = Category::create([
            'name' => 'Kategori Test',
            'image' => '',
            'description' => '',
        ]);

        $product = Product::create([
            'title' => 'Produk Test',
            'barcode' => 'AUTH-DEL-001',
            'sku' => 'SKU-AUTH-DEL-001',
            'image' => '',
            'description' => '',
            'buy_price' => 5000,
            'sell_price' => 7500,
            'stock' => 10,
            'category_id' => $category->id,
            'tax_rate' => 0,
        ]);

        $this->deleteJson("/api/v1/products/{$product->id}")->assertStatus(403);
    }

    public function test_token_with_full_abilities_can_write(): void
    {
        $user = $this->makeUser();
        $category = Category::create([
            'name' => 'Kategori Test',
            'image' => '',
            'description' => '',
        ]);

        $this->actingWithAbilities($user, ['user:read', 'products-access', 'products-create']);

        $this->postJson('/api/v1/products', [
            'title' => 'Produk Baru',
            'barcode' => 'AUTH-NEW-002',
            'category_id' => $category->id,
            'buy_price' => 5000,
            'sell_price' => 7500,
        ])->assertCreated();
    }
}
