<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Product;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ProductsImport implements ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    private int $rowCount = 0;

    public function model(array $row)
    {
        $this->rowCount++;
        $categoryName = trim($row['kategori'] ?? 'Umum');
        $category = Category::firstOrCreate(
            ['name' => $categoryName],
            ['description' => '', 'image' => 'default.png']
        );

        $barcode = (string) ($row['barcode'] ?? '');

        return Product::updateOrCreate(
            ['barcode' => $barcode],
            [
                'sku' => $row['sku'] ?? $barcode,
                'title' => $row['nama'] ?? '',
                'description' => $row['deskripsi'] ?? '',
                'category_id' => $category->id,
                'buy_price' => (int) ($row['harga_beli'] ?? 0),
                'sell_price' => (int) ($row['harga_jual'] ?? 0),
                'stock' => (int) ($row['stok'] ?? 0),
                'min_stock' => (int) ($row['min_stok'] ?? 0),
                'max_stock' => (int) ($row['max_stok'] ?? 0),
                'tax_type' => $row['tipe_pajak'] ?? 'exclusive',
                'tax_rate' => (float) ($row['tarif_pajak'] ?? 11.00),
            ]
        );
    }

    public function rules(): array
    {
        return [
            'barcode' => ['required'],
            'nama' => ['required', 'string', 'max:255'],
            'harga_beli' => ['nullable', 'numeric', 'min:0'],
            'harga_jual' => ['nullable', 'numeric', 'min:0'],
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

    public function batchSize(): int
    {
        return 100;
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
