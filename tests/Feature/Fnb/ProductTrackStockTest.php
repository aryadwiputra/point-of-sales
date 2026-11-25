<?php

namespace Tests\Feature\Fnb;

use App\Models\Category;
use App\Models\Product;
use App\Services\ReorderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTrackStockTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(string $title, bool $trackStock, int $stock, int $minStock): Product
    {
        static $seq = 0;
        $seq++;

        return Product::create([
            'category_id' => Category::firstOrCreate(['name' => 'Minuman'])->id,
            'title' => $title,
            'sku' => 'TRACK-'.$seq,
            'barcode' => null,
            'buy_price' => 1000,
            'sell_price' => 5000,
            'stock' => $stock,
            'min_stock' => $minStock,
            'max_stock' => 50,
            'track_stock' => $trackStock,
            'tax_rate' => 0,
        ]);
    }

    public function test_non_stock_product_is_never_low_stock(): void
    {
        $product = $this->makeProduct('Es Kopi', false, 0, 10);

        $this->assertFalse($product->isLowStock());
        $this->assertFalse($product->tracksStock());
    }

    public function test_reorder_ignores_non_stock_products(): void
    {
        $tracked = $this->makeProduct('Biji Kopi', true, 2, 10);
        $this->makeProduct('Es Kopi', false, 0, 10);

        $ids = app(ReorderService::class)->getLowStockProducts()->pluck('id')->all();

        $this->assertContains($tracked->id, $ids);
        $this->assertCount(1, $ids);
    }
}
