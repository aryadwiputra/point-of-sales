<?php

namespace Tests\Feature\DineIn;

use App\Models\Category;
use App\Models\DineArea;
use App\Models\DineBill;
use App\Models\DineOrder;
use App\Models\DineSession;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillDineBillsTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_is_idempotent(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        $cashier = User::where('email', 'cashier@gmail.com')->first();
        $cashier->markEmailAsVerified();
        $warehouse = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Kategori Test',
            'image' => 'categories/test.jpg',
            'description' => 'Kategori untuk test',
        ]);
        $area = DineArea::create(['name' => 'Indoor', 'is_active' => true]);
        $table = DiningTable::create([
            'dine_area_id' => $area->id,
            'name' => 'Meja Legacy',
            'is_active' => true,
            'capacity' => 4,
        ]);
        $product = Product::create([
            'title' => 'Menu Legacy',
            'sku' => 'SKU-LEG-'.uniqid(),
            'buy_price' => 4000,
            'sell_price' => 10000,
            'stock' => 10,
            'image' => 'products/test.jpg',
            'barcode' => 'BC-LEG-'.uniqid(),
            'description' => 'Produk test',
            'tax_rate' => 0,
            'category_id' => $category->id,
        ]);
        $product->warehouses()->attach($warehouse->id, ['stock' => 10]);

        $transaction = Transaction::create([
            'cashier_id' => $cashier->id,
            'warehouse_id' => $warehouse->id,
            'invoice' => 'TRX-LEGACY-1',
            'cash' => 20000,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 20000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'order_type' => 'in_store',
        ]);
        $order = DineOrder::create([
            'dine_table_id' => $table->id,
            'access_token' => 'legacy-'.uniqid(),
            'status' => DineOrder::STATUS_ACCEPTED,
            'source' => DineOrder::SOURCE_QR,
            'transaction_id' => $transaction->id,
            'subtotal' => 20000,
            'item_count' => 2,
            'cashier_id' => $cashier->id,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 10000,
            'unit_cost' => 4000,
            'tax_type' => 'exclusive',
            'tax_rate' => 0,
        ]);

        $this->artisan('dine:backfill-bills')->assertSuccessful();
        $this->artisan('dine:backfill-bills')->assertSuccessful();

        $this->assertSame(1, DineSession::count());
        $this->assertSame(1, DineBill::count());
        $this->assertSame(DineSession::STATUS_CLOSED, DineSession::first()->status);
        $this->assertSame(DineBill::STATUS_SETTLED, DineBill::first()->status);
        $this->assertSame($transaction->id, DineBill::first()->transaction_id);
        $this->assertNotNull($order->fresh()->dine_session_id);
        $this->assertNotNull($order->fresh()->items()->first()->dine_bill_id);
    }
}
