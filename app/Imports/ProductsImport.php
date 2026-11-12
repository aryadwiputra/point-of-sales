<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Warehouse;
use App\Services\StockMutationService;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ProductsImport implements ToModel, WithChunkReading, WithHeadingRow, WithValidation
{
    private int $rowCount = 0;

    public function __construct(private readonly ?int $targetWarehouseId = null) {}

    public function model(array $row)
    {
        $barcode = trim((string) ($row['barcode'] ?? ''));
        if ($barcode === '') {
            return null;
        }

        $this->rowCount++;

        $categoryName = trim((string) ($row['kategori'] ?? $row['category'] ?? 'Umum'));
        if ($categoryName === '') {
            $categoryName = 'Umum';
        }

        $category = Category::firstOrCreate(
            ['name' => $categoryName],
            ['description' => '', 'image' => null]
        );

        $sku = trim((string) ($row['sku'] ?? ''));
        if ($sku === '') {
            $sku = $barcode;
        }

        // Avoid unique SKU conflict with other products
        if (Product::where('sku', $sku)->where('barcode', '!=', $barcode)->exists()) {
            $sku = $barcode;
            if (Product::where('sku', $sku)->where('barcode', '!=', $barcode)->exists()) {
                $sku = $barcode.'-'.uniqid();
            }
        }

        $title = trim((string) ($row['nama'] ?? $row['title'] ?? $row['name'] ?? ''));
        $description = (string) ($row['deskripsi'] ?? $row['description'] ?? '');
        $buyPrice = (int) ($row['harga_beli'] ?? $row['buy_price'] ?? 0);
        $sellPrice = (int) ($row['harga_jual'] ?? $row['sell_price'] ?? 0);
        $stock = (int) ($row['stok'] ?? $row['stock'] ?? 0);
        $minStock = (int) ($row['min_stok'] ?? $row['min_stock'] ?? 0);
        $maxStock = (int) ($row['max_stok'] ?? $row['max_stock'] ?? 0);
        $taxType = (string) ($row['tipe_pajak'] ?? $row['tax_type'] ?? 'exclusive');
        $taxRate = (float) ($row['tarif_pajak'] ?? $row['tax_rate'] ?? 11.00);

        $product = Product::updateOrCreate(
            ['barcode' => $barcode],
            [
                'sku' => $sku,
                'title' => $title !== '' ? $title : $barcode,
                'description' => $description,
                'category_id' => $category->id,
                'buy_price' => $buyPrice,
                'sell_price' => $sellPrice,
                'stock' => $stock,
                'min_stock' => $minStock,
                'max_stock' => $maxStock,
                'tax_type' => in_array($taxType, ['exclusive', 'inclusive']) ? $taxType : 'exclusive',
                'tax_rate' => $taxRate,
            ]
        );

        $warehouse = ($this->targetWarehouseId ? Warehouse::find($this->targetWarehouseId) : null)
            ?? Warehouse::active()->where('type', 'main')->orderBy('sort_order')->orderBy('code')->first()
            ?? Warehouse::active()->orderBy('sort_order')->orderBy('code')->first();

        if ($warehouse) {
            ProductWarehouse::updateOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                ['stock' => $stock]
            );
        }

        if ($product->wasRecentlyCreated && $stock > 0) {
            try {
                app(StockMutationService::class)->recordInitialStock($product, auth()->id(), $warehouse?->id);
            } catch (\Throwable) {
                // Ignore stock mutation error if running without user session
            }
        }

        // Return null to prevent ToModel from attempting a second INSERT
        return null;
    }

    public function rules(): array
    {
        return [
            'barcode' => ['required'],
        ];
    }

    public function customValidationMessages()
    {
        return [
            'barcode.required' => 'Barcode wajib diisi.',
            'barcode.unique' => 'Barcode sudah terdaftar.',
            'nama.required' => 'Nama produk wajib diisi.',
        ];
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
