<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\DineArea;
use App\Models\DineOrder;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class V1DineInApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private DineArea $area;

    private DiningTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->markEmailAsVerified();

        $this->area = DineArea::create([
            'name' => 'Area Utama',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->table = DiningTable::create([
            'dine_area_id' => $this->area->id,
            'name' => 'Meja 1',
            'is_active' => true,
            'capacity' => 4,
            'sort_order' => 0,
            'token' => (string) Str::uuid(),
        ]);

        Sanctum::actingAs($this->user, ['*']);
    }

    private function makeOrder(array $overrides = []): DineOrder
    {
        return DineOrder::create(array_merge([
            'dine_table_id' => $this->table->id,
            'status' => DineOrder::STATUS_SUBMITTED,
            'payment_option' => 'pay_at_counter',
            'subtotal' => 20000,
            'item_count' => 2,
            'submitted_at' => now(),
        ], $overrides));
    }

    public function test_dine_areas_index(): void
    {
        $this->getJson('/api/v1/dine-areas')
            ->assertOk()
            ->assertJsonPath('data.areas.0.name', 'Area Utama');
    }

    public function test_dine_area_store(): void
    {
        $this->postJson('/api/v1/dine-areas', [
            'name' => 'Area VIP',
            'sort_order' => 1,
            'is_active' => true,
        ])->assertStatus(201);

        $this->assertDatabaseHas('dine_areas', ['name' => 'Area VIP']);
    }

    public function test_dine_area_update(): void
    {
        $this->patchJson("/api/v1/dine-areas/{$this->area->id}", [
            'name' => 'Area Renamed',
        ])->assertOk();

        $this->assertDatabaseHas('dine_areas', [
            'id' => $this->area->id,
            'name' => 'Area Renamed',
        ]);
    }

    public function test_dine_area_destroy_rejects_when_has_tables(): void
    {
        $this->deleteJson("/api/v1/dine-areas/{$this->area->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('dine_areas', ['id' => $this->area->id]);
    }

    public function test_dine_area_destroy_succeeds_when_empty(): void
    {
        $empty = DineArea::create(['name' => 'Area Kosong', 'is_active' => true, 'sort_order' => 1]);

        $this->deleteJson("/api/v1/dine-areas/{$empty->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('dine_areas', ['id' => $empty->id]);
    }

    public function test_dine_area_store_requires_create_ability(): void
    {
        Sanctum::actingAs($this->user, ['dine-tables-access']);

        $this->postJson('/api/v1/dine-areas', [
            'name' => 'No Perm',
        ])->assertForbidden();
    }

    public function test_dine_tables_index(): void
    {
        $this->getJson('/api/v1/dine-tables')
            ->assertOk()
            ->assertJsonPath('data.tables.0.name', 'Meja 1')
            ->assertJsonPath('data.areas.0.name', 'Area Utama');
    }

    public function test_dine_table_store(): void
    {
        $this->postJson('/api/v1/dine-tables', [
            'dine_area_id' => $this->area->id,
            'name' => 'Meja 2',
            'capacity' => 6,
            'is_active' => true,
        ])->assertStatus(201);

        $this->assertDatabaseHas('dine_tables', ['name' => 'Meja 2']);
    }

    public function test_dine_table_update(): void
    {
        $this->patchJson("/api/v1/dine-tables/{$this->table->id}", [
            'name' => 'Meja 1A',
        ])->assertOk();

        $this->assertDatabaseHas('dine_tables', [
            'id' => $this->table->id,
            'name' => 'Meja 1A',
        ]);
    }

    public function test_dine_table_destroy(): void
    {
        $this->deleteJson("/api/v1/dine-tables/{$this->table->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('dine_tables', ['id' => $this->table->id]);
    }

    public function test_dine_table_qr_returns_png(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('imagick extension not installed; PNG QR generation requires it.');
        }

        $response = $this->getJson("/api/v1/dine-tables/{$this->table->id}/qr");

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
    }

    public function test_dine_table_store_requires_create_ability(): void
    {
        Sanctum::actingAs($this->user, ['dine-tables-access']);

        $this->postJson('/api/v1/dine-tables', [
            'name' => 'No Perm',
        ])->assertForbidden();
    }

    public function test_dine_orders_index_lists_active_orders(): void
    {
        $order = $this->makeOrder();

        $this->getJson('/api/v1/dine-orders')
            ->assertOk()
            ->assertJsonPath('data.orders.0.id', $order->id);
    }

    public function test_dine_order_accept_requires_open_shift(): void
    {
        $order = $this->makeOrder();

        $this->postJson("/api/v1/dine-orders/{$order->id}/accept")
            ->assertStatus(422);
    }

    public function test_dine_order_reject(): void
    {
        $order = $this->makeOrder();

        $this->postJson("/api/v1/dine-orders/{$order->id}/reject", [
            'reason' => 'Stok habis',
        ])->assertOk();

        $this->assertDatabaseHas('dine_orders', [
            'id' => $order->id,
            'status' => DineOrder::STATUS_REJECTED,
        ]);
    }

    public function test_dine_order_reject_requires_process_ability(): void
    {
        Sanctum::actingAs($this->user, ['dine-orders-access']);
        $order = $this->makeOrder();

        $this->postJson("/api/v1/dine-orders/{$order->id}/reject", [
            'reason' => 'No perm',
        ])->assertForbidden();
    }

    public function test_dine_orders_index_requires_access_ability(): void
    {
        Sanctum::actingAs($this->user, ['products-access']);

        $this->getJson('/api/v1/dine-orders')->assertForbidden();
    }

    public function test_dine_order_accept_with_open_shift_creates_transaction(): void
    {
        $warehouse = Warehouse::create([
            'code' => 'WH-DINE',
            'name' => 'Gudang Dine',
            'status' => 'active',
        ]);

        $category = Category::create([
            'name' => 'Kategori Dine',
            'image' => '',
            'description' => '',
        ]);

        $product = Product::create([
            'title' => 'Produk Dine',
            'barcode' => 'DINE-'.uniqid(),
            'sku' => 'SKU-DINE-'.uniqid(),
            'image' => '',
            'description' => 'Produk dine-in',
            'category_id' => $category->id,
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => 50,
            'tax_rate' => 0,
            'is_active' => true,
        ]);
        $product->warehouses()->attach($warehouse->id, ['stock' => 50]);

        $order = $this->makeOrder();
        $order->items()->create([
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 10000,
        ]);

        app(CashierShiftService::class)->openShift($this->user, $this->user, 0, null, $warehouse->id);

        $response = $this->postJson("/api/v1/dine-orders/{$order->id}/accept");
        $response->assertOk();

        $this->assertDatabaseHas('dine_orders', [
            'id' => $order->id,
            'status' => DineOrder::STATUS_ACCEPTED,
        ]);
        $this->assertDatabaseHas('product_warehouse', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'stock' => 48,
        ]);
    }
}
