<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MasterDataApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_customers_index_paginates_and_searches(): void
    {
        Customer::create([
            'name' => 'Budi Santoso',
            'no_telp' => '081111',
            'address' => 'Jakarta',
            'is_loyalty_member' => false,
        ]);
        Customer::create([
            'name' => 'Ani',
            'no_telp' => '082222',
            'address' => 'Bandung',
            'is_loyalty_member' => false,
        ]);

        $this->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/customers?search=budi')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Budi Santoso');
    }

    public function test_customers_store_validates_unique_phone(): void
    {
        Customer::create([
            'name' => 'Budi',
            'no_telp' => '081111',
            'address' => 'Jakarta',
            'is_loyalty_member' => false,
        ]);

        $this->postJson('/api/v1/customers', [
            'name' => 'Dup',
            'no_telp' => '081111',
            'address' => 'Surabaya',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('no_telp');

        $this->postJson('/api/v1/customers', [
            'name' => 'Siti',
            'no_telp' => '089999',
            'address' => 'Medan',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Siti');
    }

    public function test_customers_update_and_delete(): void
    {
        $customer = Customer::create([
            'name' => 'Budi',
            'no_telp' => '081111',
            'address' => 'Jakarta',
            'is_loyalty_member' => false,
        ]);

        $this->putJson("/api/v1/customers/{$customer->id}", ['name' => 'Budi Update'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Budi Update');

        $this->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_categories_crud(): void
    {
        $response = $this->postJson('/api/v1/categories', [
            'name' => 'Minuman',
            'description' => 'Kategori minuman',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Minuman');

        $categoryId = $response->json('data.id');

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->putJson("/api/v1/categories/{$categoryId}", ['name' => 'Minuman Segar'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Minuman Segar');

        $this->deleteJson("/api/v1/categories/{$categoryId}")->assertStatus(204);
    }

    public function test_categories_duplicate_name_rejected(): void
    {
        Category::create(['name' => 'Minuman', 'image' => '', 'description' => '']);

        $this->postJson('/api/v1/categories', ['name' => 'Minuman'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_warehouses_crud(): void
    {
        $response = $this->postJson('/api/v1/warehouses', [
     'code' => 'WH-A',
     'name' => 'Gudang A',
     'type' => 'branch',
     'is_active' => true,
 ])->assertCreated()
            ->assertJsonPath('data.code', 'WH-A');

        $warehouseId = $response->json('data.id');

        $this->getJson('/api/v1/warehouses')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->putJson("/api/v1/warehouses/{$warehouseId}", ['name' => 'Gudang Alpha'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Gudang Alpha');

        $this->deleteJson("/api/v1/warehouses/{$warehouseId}")->assertStatus(204);
    }

    public function test_warehouses_duplicate_code_rejected(): void
    {
        Warehouse::create(['code' => 'WH-A', 'name' => 'Gudang A']);

        $this->postJson('/api/v1/warehouses', ['code' => 'WH-A', 'name' => 'Gudang B'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_suppliers_crud(): void
    {
        $response = $this->postJson('/api/v1/suppliers', [
            'name' => 'PT Sumber Jaya',
            'phone' => '0211234',
            'email' => 'sales@jaya.com',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'PT Sumber Jaya');

        $supplierId = $response->json('data.id');

        $this->getJson('/api/v1/suppliers?search=jaya')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->putJson("/api/v1/suppliers/{$supplierId}", ['phone' => '0219999'])
            ->assertOk()
            ->assertJsonPath('data.phone', '0219999');

        $this->deleteJson("/api/v1/suppliers/{$supplierId}")->assertStatus(204);
    }
}
