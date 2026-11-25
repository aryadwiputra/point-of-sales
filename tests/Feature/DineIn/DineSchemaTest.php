<?php

namespace Tests\Feature\DineIn;

use App\Models\Category;
use App\Models\DineBill;
use App\Models\DineOrder;
use App\Models\DineOrderItem;
use App\Models\DineSession;
use App\Models\DiningTable;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DineSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_dine_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('dine_sessions'));
        $this->assertTrue(Schema::hasTable('dine_bills'));
        $this->assertTrue(Schema::hasTable('dine_order_item_batch_allocations'));
        $this->assertTrue(Schema::hasColumns('dine_sessions', [
            'dine_table_id', 'status', 'guest_count', 'opened_by', 'opened_at',
            'closed_at', 'merged_into_session_id', 'notes',
        ]));
        $this->assertTrue(Schema::hasColumns('dine_bills', [
            'dine_session_id', 'sequence', 'status', 'customer_id', 'subtotal',
            'discount_total', 'tax_total', 'grand_total', 'transaction_id',
            'settled_at', 'created_by',
        ]));
        $this->assertTrue(Schema::hasColumns('dine_orders', ['dine_session_id', 'source']));
        $this->assertTrue(Schema::hasColumns('dine_order_items', [
            'dine_bill_id', 'unit_cost', 'tax_type', 'tax_rate',
            'voided_at', 'voided_by', 'void_reason',
        ]));
        $this->assertTrue(Schema::hasColumns('dine_order_item_batch_allocations', [
            'dine_order_item_id', 'product_batch_id', 'qty',
        ]));
    }

    public function test_dine_models_expose_relations_and_defaults(): void
    {
        $table = DiningTable::create([
            'name' => 'Meja Skema',
            'is_active' => true,
            'capacity' => 4,
        ]);

        $session = DineSession::create([
            'dine_table_id' => $table->id,
            'opened_at' => now(),
        ]);
        $bill = DineBill::create([
            'dine_session_id' => $session->id,
            'sequence' => 1,
            'status' => DineBill::STATUS_OPEN,
        ]);
        $order = DineOrder::create([
            'dine_table_id' => $table->id,
            'dine_session_id' => $session->id,
            'access_token' => 'schema-token',
            'status' => DineOrder::STATUS_ACCEPTED,
            'source' => DineOrder::SOURCE_QR,
        ]);
        $item = DineOrderItem::create([
            'dine_order_id' => $order->id,
            'dine_bill_id' => $bill->id,
            'product_id' => $this->product()->id,
            'qty' => 1,
            'price' => 1000,
        ]);

        $this->assertTrue($table->sessions()->exists());
        $this->assertSame(1, $session->bills()->count());
        $this->assertSame($session->id, $order->session->id);
        $this->assertSame($bill->id, $item->bill->id);
        $this->assertSame('open', $session->status);
        $this->assertSame('qr', $order->source);
        $this->assertSame(0, $item->unit_cost);
        $this->assertFalse($item->isVoided());
    }

    private function product(): Product
    {
        $category = Category::create([
            'name' => 'Kategori Skema',
            'image' => 'categories/test.jpg',
            'description' => 'Kategori untuk test',
        ]);

        return Product::create([
            'title' => 'Produk Skema',
            'sku' => 'SKU-SCHEMA-'.uniqid(),
            'buy_price' => 500,
            'sell_price' => 1000,
            'stock' => 5,
            'image' => 'products/test.jpg',
            'barcode' => 'BC-SCHEMA-'.uniqid(),
            'description' => 'Produk test',
            'tax_rate' => 0,
            'category_id' => $category->id,
        ]);
    }
}
