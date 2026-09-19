<?php

namespace Tests\Feature\Console;

use App\Models\Product;
use App\Models\Category;
use App\Models\StockMutation;
use App\Models\StockOpname;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditOutletCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_command_is_read_only_and_reports_system_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('outlet:audit')
            ->expectsOutputToContain('Outlet data audit (read-only)')
            ->expectsOutputToContain('Outlets: 1')
            ->assertSuccessful();
    }

    public function test_strict_audit_passes_for_clean_system_seed(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('outlet:audit', ['--strict' => true])
            ->assertSuccessful();
    }

    public function test_legacy_audit_is_read_only(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('outlet:legacy-audit')
            ->expectsOutputToContain('Legacy outlet audit (read-only; no backfill performed)')
            ->assertSuccessful();
    }

    public function test_legacy_audit_backfills_only_unambiguous_stock_mutations(): void
    {
        $this->seed(DatabaseSeeder::class);
        $warehouse = Warehouse::create([
            'code' => 'BACKFILL',
            'name' => 'Gudang Backfill',
            'type' => 'branch',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $category = Category::create([
            'name' => 'Kategori Backfill',
            'image' => 'categories/backfill.jpg',
            'description' => 'Kategori audit',
        ]);
        $product = Product::create([
            'title' => 'Produk Backfill',
            'sku' => 'SKU-BACKFILL',
            'barcode' => 'BC-BACKFILL',
            'image' => 'products/backfill.jpg',
            'description' => 'Produk audit',
            'category_id' => $category->id,
            'buy_price' => 1000,
            'sell_price' => 1500,
            'stock' => 0,
            'tax_rate' => 0,
        ]);
        $stockOpname = StockOpname::create([
            'code' => 'SO-BACKFILL',
            'warehouse_id' => $warehouse->id,
            'status' => 'finalized',
        ]);
        $mapped = StockMutation::create([
            'product_id' => $product->id,
            'reference_type' => 'stock_opname',
            'reference_id' => $stockOpname->id,
            'mutation_type' => 'adjustment',
            'qty' => 1,
            'stock_before' => 0,
            'stock_after' => 1,
        ]);
        $ambiguous = StockMutation::create([
            'product_id' => $product->id,
            'reference_type' => 'legacy_import',
            'reference_id' => 999999,
            'mutation_type' => 'in',
            'qty' => 1,
            'stock_before' => 0,
            'stock_after' => 1,
        ]);

        $this->artisan('outlet:legacy-audit', ['--backfill' => true])
            ->expectsOutputToContain('Stock mutations backfilled: 1')
            ->expectsOutputToContain('Stock mutations left ambiguous: 1')
            ->assertSuccessful();

        $this->assertSame($warehouse->id, $mapped->fresh()->warehouse_id);
        $this->assertNull($ambiguous->fresh()->warehouse_id);
    }
}
