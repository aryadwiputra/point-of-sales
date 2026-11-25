<?php

namespace Tests\Feature\DineIn;

use App\Models\Category;
use App\Models\DineArea;
use App\Models\DineBill;
use App\Models\DineOrder;
use App\Models\DineSession;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use App\Services\DineBillSettlementService;
use App\Services\DineSessionService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DineBillSettlementTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private User $cashier;

    private Warehouse $warehouse;

    private Category $category;

    private DiningTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);
        $this->cashier = User::where('email', 'cashier@gmail.com')->first();
        $this->cashier->markEmailAsVerified();
        $this->warehouse = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $this->category = Category::create([
            'name' => 'Kategori Test',
            'image' => 'categories/test.jpg',
            'description' => 'Kategori untuk test',
        ]);
        $area = DineArea::create(['name' => 'Indoor', 'is_active' => true]);
        $this->table = DiningTable::create([
            'dine_area_id' => $area->id,
            'name' => 'Meja Settle',
            'is_active' => true,
            'capacity' => 4,
        ]);
        app(CashierShiftService::class)->openShift(
            $this->cashier,
            $this->cashier,
            0,
            null,
            $this->warehouse->id,
        );
    }

    private function makeBill(
        int $qty = 2,
        int $price = 10000,
        int $buyPrice = 5000,
        string $taxType = 'exclusive',
        float $taxRate = 0
    ): array {
        self::$seq++;
        $product = Product::create([
            'title' => 'Menu Settle '.self::$seq,
            'sku' => 'SKU-SET-'.self::$seq,
            'buy_price' => $buyPrice,
            'sell_price' => $price,
            'stock' => 10,
            'image' => 'products/test.jpg',
            'barcode' => 'BC-SET-'.self::$seq,
            'description' => 'Produk test',
            'tax_rate' => $taxRate,
            'tax_type' => $taxType,
            'category_id' => $this->category->id,
        ]);
        $product->warehouses()->attach($this->warehouse->id, ['stock' => 10]);

        $session = app(DineSessionService::class)->open($this->table, 2, null, $this->cashier->id);
        $bill = DineBill::create([
            'dine_session_id' => $session->id,
            'sequence' => 1,
            'status' => DineBill::STATUS_OPEN,
            'subtotal' => $price * $qty,
            'grand_total' => $price * $qty,
        ]);
        $order = DineOrder::create([
            'dine_table_id' => $this->table->id,
            'dine_session_id' => $session->id,
            'access_token' => 'settle-'.uniqid(),
            'status' => DineOrder::STATUS_ACCEPTED,
            'source' => DineOrder::SOURCE_QR,
            'subtotal' => $price * $qty,
        ]);
        $order->items()->create([
            'dine_bill_id' => $bill->id,
            'product_id' => $product->id,
            'qty' => $qty,
            'price' => $price,
            'unit_cost' => $buyPrice,
            'tax_type' => $taxType,
            'tax_rate' => $taxRate,
        ]);

        return [$bill, $order, $product, $session];
    }

    public function test_cash_settlement_creates_one_transaction_and_keeps_stock(): void
    {
        [$bill, $order, $product] = $this->makeBill(qty: 2, price: 10000, buyPrice: 5000);

        $result = app(DineBillSettlementService::class)
            ->settleCash($bill, $this->cashier->id, 30000);

        $this->assertSame(1, Transaction::count());
        $this->assertSame('settled', $result->bill->status);
        $this->assertNotNull($result->bill->settled_at);
        $this->assertSame(20000, $result->subtotal);
        $this->assertSame(20000, $result->grandTotal);
        $this->assertSame(30000, (int) $result->transaction->cash);
        $this->assertSame(10000, (int) $result->transaction->change);
        $this->assertSame(10000, (int) $result->transaction->profits()->sum('total'));
        $this->assertSame(DineOrder::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertSame(DineSession::STATUS_CLOSED, $bill->session->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(10, ProductWarehouse::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)->first()->stock);
    }

    public function test_exclusive_tax_is_added_to_grand_total(): void
    {
        [$bill] = $this->makeBill(qty: 1, price: 20000, taxType: 'exclusive', taxRate: 11);

        $result = app(DineBillSettlementService::class)
            ->settleCash($bill, $this->cashier->id, 30000);

        $this->assertSame(2200, $result->taxTotal);
        $this->assertSame(22200, $result->grandTotal);
        $this->assertSame(2200, (int) $result->transaction->tax_total);
    }

    public function test_inclusive_tax_is_not_added_again_to_grand_total(): void
    {
        [$bill] = $this->makeBill(qty: 1, price: 11100, taxType: 'inclusive', taxRate: 11);

        $result = app(DineBillSettlementService::class)
            ->settleCash($bill, $this->cashier->id, 20000);

        $this->assertSame(1100, $result->taxTotal);
        $this->assertSame(11100, $result->grandTotal);
        $this->assertSame(1100, (int) $result->transaction->tax_total);
    }
}
