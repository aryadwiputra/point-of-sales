<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function collection()
    {
        return Product::with('category')->orderBy('title')->get();
    }

    public function headings(): array
    {
        return ['Barcode', 'SKU', 'Nama', 'Kategori', 'Harga Beli', 'Harga Jual', 'Stok', 'Min Stok', 'Max Stok', 'Tipe Pajak', 'Tarif Pajak'];
    }

    public function map($product): array
    {
        return [
            $product->barcode,
            $product->sku,
            $product->title,
            $product->category?->name ?? '',
            (int) $product->buy_price,
            (int) $product->sell_price,
            (int) $product->stock,
            (int) ($product->min_stock ?? 0),
            (int) ($product->max_stock ?? 0),
            $product->tax_type ?? 'exclusive',
            (float) ($product->tax_rate ?? 11.00),
        ];
    }
}
