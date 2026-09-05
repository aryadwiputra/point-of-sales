<?php

namespace Tests\Feature\DineIn;

use App\Models\Category;
use App\Models\DineOrder;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DineOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        $this->warehouse = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->category = Category::create([
            'name' => 'Kategori Dine',
            'image' => 'categories/test.jpg',
            'description' => 'Kategori untuk test dine',
        ]);

        $this->table = DiningTable::create([
            'name' => 'Meja 1',
            'is_active' => true,
            'capacity' => 4,
            'sort_order' => 0,
        ]);
    }

    private static int $seq = 0;

    private function createProduct(int $stock = 10): Product
    {
        self::$seq++;

        $product = Product::create([
            'title' => 'Menu Dine '.self::$seq,
            'sku' => 'SKU-DINEST-'.self::$seq,
            'buy_price' => 5000,
            'sell_price' => 10000,
            'stock' => $stock,
            'image' => 'products/test.jpg',
            'barcode' => 'BC-DINEST-'.self::$seq,
            'description' => 'Menu untuk test dine status',
            'tax_rate' => 0,
            'category_id' => $this->category->id,
        ]);

        $product->warehouses()->attach($this->warehouse->id, ['stock' => $stock]);

        return $product;
    }

    private function createOrder(?callable $mutate = null): DineOrder
    {
        $product = $this->createProduct();

        $order = DineOrder::create([
            'dine_table_id' => $this->table->id,
            'status' => DineOrder::STATUS_SUBMITTED,
            'payment_option' => DineOrder::PAY_AT_COUNTER,
            'subtotal' => 10000,
            'item_count' => 1,
            'access_token' => Str::uuid()->toString(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'qty' => 1,
            'price' => 10000,
        ]);

        if ($mutate) {
            $mutate($order);
        }

        return $order->fresh();
    }

    public function test_status_check_returns_minimal_order_fields(): void
    {
        $order = $this->createOrder();

        $response = $this->getJson(route('dine-order.status-check', $order->access_token));

        $response->assertOk();
        $response->assertJsonStructure([
            'order' => [
                'id', 'status', 'payment_option', 'payment_status', 'subtotal', 'item_count', 'updated_at',
            ],
        ]);

        $payload = $response->json('order');
        $this->assertArrayNotHasKey('items', $payload);
        $this->assertArrayNotHasKey('notes', $payload);
        $this->assertSame('submitted', $payload['status']);
    }

    public function test_status_check_unknown_token_returns_404(): void
    {
        $this->getJson(route('dine-order.status-check', Str::uuid()->toString()))
            ->assertNotFound();
    }

    public function test_status_check_stale_order_returns_410(): void
    {
        $order = $this->createOrder();

        DineOrder::whereKey($order->id)->update(['updated_at' => now()->subHours(25)]);

        $this->getJson(route('dine-order.status-check', $order->access_token))
            ->assertStatus(410);
    }

    public function test_status_page_still_renders_full_order_with_items(): void
    {
        $order = $this->createOrder();

        $response = $this->get(route('dine-order.status', $order->access_token));

        $response->assertOk();
        $response->assertSee('Menu Dine');
    }
}
