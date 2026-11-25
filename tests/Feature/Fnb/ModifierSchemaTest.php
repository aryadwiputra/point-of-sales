<?php

namespace Tests\Feature\Fnb;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModifierSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_modifier_tables_exist(): void
    {
        foreach ([
            'modifier_groups',
            'modifiers',
            'product_modifier_groups',
            'product_modifier_overrides',
            'cart_modifiers',
            'transaction_detail_modifiers',
            'dine_order_item_modifiers',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
        }
    }

    public function test_products_support_track_stock_and_null_barcode(): void
    {
        $this->assertTrue(Schema::hasColumns('products', ['track_stock']));

        $category = Category::create(['name' => 'Minuman']);

        $product = Product::create([
            'category_id' => $category->id,
            'title' => 'Es Teh',
            'sku' => 'TEST-ESTEH',
            'barcode' => null,
            'buy_price' => 2000,
            'sell_price' => 5000,
            'stock' => 0,
            'tax_rate' => 0,
        ]);

        $this->assertTrue($product->tracksStock());
        $this->assertNull($product->barcode);
    }

    public function test_cart_and_detail_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('carts', ['note', 'line_signature']));
        $this->assertTrue(Schema::hasColumns('transaction_details', ['note']));
    }
}
