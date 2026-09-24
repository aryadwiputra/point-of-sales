<?php

namespace Tests\Feature\Inventory;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\SalesReturn;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CashierShiftService;
use App\Services\StockTransferService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards the inventory invariant: products.stock (global aggregate) must always
 * equal the sum of product_warehouse.stock (operational source of truth) after
 * every critical stock mutation.
 */
class StockInvariantTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected Warehouse $pusat;

    protected Warehouse $branch;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class, UserSeeder::class]);

        $this->cashier = User::where('email', 'cashier@gmail.com')->first();
        $this->cashier->markEmailAsVerified();

        $this->pusat = Warehouse::create([
            'code' => 'PUSAT',
            'name' => 'Gudang Pusat',
            'type' => 'main',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->branch = Warehouse::create([
            'code' => 'CABANG',
            'name' => 'Gudang Cabang',
            'type' => 'branch',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->category = Category::create([
            'name' => 'Kategori Invariant',
            'image' => 'test.png',
            'description' => 'desc',
        ]);

        app(CashierShiftService::class)->openShift($this->cashier, $this->cashier, 0, null, $this->pusat->id);
    }

    private static int $seq = 0;

    private function createProduct(int $stock = 100): Product
    {
        self::$seq++;

        $product = Product::create([
            'title' => 'Produk Invariant '.self::$seq,
            'sku' => 'SKU-INV-'.self::$seq,
            'barcode' => 'BC-INV-'.self::$seq,
            'image' => 'product.png',
            'description' => 'desc',
            'category_id' => $this->category->id,
            'buy_price' => 1000,
            'sell_price' => 2000,
            'stock' => $stock,
            'tax_rate' => 0,
        ]);

        $product->warehouses()->attach($this->pusat->id, ['stock' => $stock]);

        return $product;
    }

    private function assertGlobalMatchesPivotSum(string $context): void
    {
        $products = Product::query()
            ->select('products.id', 'products.title', 'products.stock')
            ->selectRaw('COALESCE(SUM(product_warehouse.stock), 0) AS pivot_total')
            ->leftJoin('product_warehouse', 'product_warehouse.product_id', '=', 'products.id')
            ->groupBy('products.id', 'products.title', 'products.stock')
            ->get();

        foreach ($products as $row) {
            $this->assertSame(
                (int) $row->pivot_total,
                (int) $row->stock,
                "Global/pivot mismatch after {$context} for product #{$row->id} ({$row->title}).",
            );
        }
    }

    public function test_checkout_preserves_global_pivot_invariant(): void
    {
        $product = $this->createProduct(50);

        $this->actingAs($this->cashier)->post(route('transactions.addToCart'), [
            'product_id' => $product->id,
            'qty' => 5,
        ])->assertRedirect();

        $this->actingAs($this->cashier)
            ->post(route('transactions.store'), ['payment_method' => 'cash', 'cash' => 50000])
            ->assertRedirect();

        $this->assertSame(45, $product->fresh()->stock);
        $this->assertGlobalMatchesPivotSum('checkout');
    }

    public function test_multi_unit_checkout_preserves_global_pivot_invariant(): void
    {
        $product = $this->createProduct(50);

        Cart::create([
            'cashier_id' => $this->cashier->id,
            'warehouse_id' => $this->pusat->id,
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 24000,
            'conversion_factor' => 12,
        ]);

        $this->actingAs($this->cashier)
            ->post(route('transactions.store'), ['payment_method' => 'cash', 'cash' => 50000])
            ->assertRedirect();

        // 2 boxes x 12 = 24 base units
        $this->assertSame(26, $product->fresh()->stock);
        $this->assertGlobalMatchesPivotSum('multi-unit checkout');
    }

    public function test_stock_transfer_preserves_global_pivot_invariant(): void
    {
        $product = $this->createProduct(50);

        $transfer = app(StockTransferService::class)->createDraft(
            data: [
                'source_warehouse_id' => $this->pusat->id,
                'destination_warehouse_id' => $this->branch->id,
            ],
            items: [[
                'product_id' => $product->id,
                'qty' => 10,
            ]],
            userId: $this->cashier->id,
        );

        app(StockTransferService::class)->send($transfer, $this->cashier->id);
        $this->assertGlobalMatchesPivotSum('transfer send');

        app(StockTransferService::class)->receive($transfer->fresh(), $this->cashier->id);
        $this->assertSame(50, $product->fresh()->stock);
        $this->assertGlobalMatchesPivotSum('transfer receive');
    }

    public function test_sales_return_preserves_global_pivot_invariant(): void
    {
        $admin = User::where('email', 'arya@gmail.com')->first();
        $admin->markEmailAsVerified();
        app(CashierShiftService::class)->openShift($admin, $admin, 0, null, $this->pusat->id);

        $product = $this->createProduct(10);

        $transaction = Transaction::create([
            'cashier_id' => $admin->id,
            'warehouse_id' => $this->pusat->id,
            'invoice' => 'TRX-'.Str::upper(Str::random(8)),
            'cash' => 2000,
            'change' => 0,
            'discount' => 0,
            'shipping_cost' => 0,
            'grand_total' => 2000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $detail = $transaction->details()->create([
            'product_id' => $product->id,
            'qty' => 1,
            'conversion_factor' => 1,
            'price' => 2000,
        ]);

        $salesReturn = SalesReturn::create([
            'code' => 'SR-INV-'.Str::upper(Str::random(6)),
            'transaction_id' => $transaction->id,
            'warehouse_id' => $this->pusat->id,
            'cashier_id' => $admin->id,
            'status' => 'draft',
            'return_type' => 'refund_cash',
            'refund_amount' => 2000,
            'credited_amount' => 0,
            'total_return_amount' => 2000,
        ]);

        $salesReturn->items()->create([
            'transaction_detail_id' => $detail->id,
            'product_id' => $product->id,
            'qty_sold' => 1,
            'qty_returned_before' => 0,
            'qty_return' => 1,
            'unit_price' => 2000,
            'subtotal' => 2000,
            'return_reason' => 'Test invariant',
            'restock_to_inventory' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('sales-returns.complete', $salesReturn))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(11, $product->fresh()->stock);
        $this->assertGlobalMatchesPivotSum('sales return');
    }

    public function test_reconcile_command_confirms_consistency_after_operations(): void
    {
        $product = $this->createProduct(50);

        $this->actingAs($this->cashier)->post(route('transactions.addToCart'), [
            'product_id' => $product->id,
            'qty' => 5,
        ])->assertRedirect();

        $this->actingAs($this->cashier)
            ->post(route('transactions.store'), ['payment_method' => 'cash', 'cash' => 50000])
            ->assertRedirect();

        $this->artisan('inventory:reconcile')
            ->expectsOutputToContain('Inventory is consistent')
            ->assertSuccessful();
    }

    public function test_audit_multiuint_command_is_read_only_when_consistent(): void
    {
        $this->createProduct(10);

        $snapshot = ProductWarehouse::query()->pluck('stock', 'id')->all();

        $this->artisan('inventory:audit-multiuint')
            ->expectsOutputToContain('No multi-unit drift candidates found')
            ->assertSuccessful();

        $this->assertSame($snapshot, ProductWarehouse::query()->pluck('stock', 'id')->all());
    }

    public function test_audit_multiuint_command_reports_multiunit_sales_return(): void
    {
        $product = $this->createProduct(10);

        $transaction = Transaction::create([
            'cashier_id' => $this->cashier->id,
            'warehouse_id' => $this->pusat->id,
            'invoice' => 'TRX-'.Str::upper(Str::random(8)),
            'cash' => 24000,
            'change' => 0,
            'discount' => 0,
            'shipping_cost' => 0,
            'grand_total' => 24000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $detail = $transaction->details()->create([
            'product_id' => $product->id,
            'qty' => 1,
            'conversion_factor' => 12,
            'price' => 24000,
        ]);

        $salesReturn = SalesReturn::create([
            'code' => 'SR-AUDIT-'.Str::upper(Str::random(6)),
            'transaction_id' => $transaction->id,
            'warehouse_id' => $this->pusat->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'completed',
            'return_type' => 'refund_cash',
            'refund_amount' => 24000,
            'credited_amount' => 0,
            'total_return_amount' => 24000,
            'completed_at' => now(),
        ]);

        $salesReturn->items()->create([
            'transaction_detail_id' => $detail->id,
            'product_id' => $product->id,
            'qty_sold' => 1,
            'qty_returned_before' => 0,
            'qty_return' => 1,
            'unit_price' => 24000,
            'subtotal' => 24000,
            'return_reason' => 'Audit drift',
            'restock_to_inventory' => true,
        ]);

        $this->artisan('inventory:audit-multiuint')
            ->expectsOutputToContain('Sales returns restocked with a conversion factor > 1: 1')
            ->expectsOutputToContain($salesReturn->code)
            ->assertSuccessful();
    }
}
