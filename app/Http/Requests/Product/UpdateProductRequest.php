<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'image' => ['nullable', 'image', 'max:2048'],
            'sku' => ['required', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product?->id)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category_id' => ['required', 'exists:categories,id'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'product_units' => ['required', 'array', 'min:1'],
            'product_units.*.id' => ['nullable', 'integer'],
            'product_units.*.label' => ['required', 'string', 'max:255'],
            'product_units.*.conversion_qty' => ['required', 'numeric', 'min:0.001'],
            'product_units.*.is_base_unit' => ['nullable', 'boolean'],
            'product_units.*.buy_price' => ['required', 'integer', 'min:0'],
            'product_units.*.sell_price' => ['required', 'integer', 'min:0'],
            'product_units.*.barcode' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(fn ($validator) => $this->validateUnits($validator));
    }

    public function normalizedData(): array
    {
        $validated = $this->validated();
        $validated['product_units'] = $this->normalizeProductUnits($validated['product_units']);

        return $validated;
    }

    private function validateUnits($validator): void
    {
        $product = $this->route('product');
        $units = collect($this->input('product_units', []));

        if ($units->isEmpty()) {
            return;
        }

        $baseUnits = $units->filter(
            fn (array $unit) => filter_var($unit['is_base_unit'] ?? false, FILTER_VALIDATE_BOOL)
        );

        if ($baseUnits->count() !== 1) {
            $validator->errors()->add('product_units', 'Pilih tepat satu satuan dasar.');
        }

        $seenBarcodes = [];

        foreach ($units as $index => $unit) {
            $barcode = trim((string) ($unit['barcode'] ?? ''));
            $unitId = $unit['id'] ?? null;
            $isBaseUnit = filter_var($unit['is_base_unit'] ?? false, FILTER_VALIDATE_BOOL);

            if ($unitId && $product && ! ProductUnit::whereKey($unitId)->where('product_id', $product->id)->exists()) {
                $validator->errors()->add("product_units.{$index}.id", 'Satuan tidak valid untuk produk ini.');
            }

            if ($isBaseUnit && abs((float) ($unit['conversion_qty'] ?? 0) - 1.0) > 0.0001) {
                $validator->errors()->add("product_units.{$index}.conversion_qty", 'Satuan dasar harus bernilai 1.');
            }

            if ($barcode === '') {
                continue;
            }

            $barcodeKey = strtolower($barcode);

            if (isset($seenBarcodes[$barcodeKey])) {
                $validator->errors()->add("product_units.{$index}.barcode", 'Barcode satuan tidak boleh duplikat.');
            }

            $seenBarcodes[$barcodeKey] = true;

            $unitExists = ProductUnit::where('barcode', $barcode)
                ->when($product, fn ($query) => $query->where('product_id', '!=', $product->id))
                ->exists();

            if ($unitExists) {
                $validator->errors()->add("product_units.{$index}.barcode", 'Barcode satuan sudah digunakan.');
            }

            $productExists = Product::where('barcode', $barcode)
                ->when($product, fn ($query) => $query->where('id', '!=', $product->id))
                ->exists();

            if ($productExists) {
                $validator->errors()->add("product_units.{$index}.barcode", 'Barcode sudah digunakan produk lain.');
            }
        }
    }

    private function normalizeProductUnits(array $units): array
    {
        return collect($units)
            ->map(function (array $unit) {
                $isBaseUnit = filter_var($unit['is_base_unit'] ?? false, FILTER_VALIDATE_BOOL);

                return [
                    'label' => trim($unit['label']),
                    'conversion_qty' => $isBaseUnit ? 1 : (float) $unit['conversion_qty'],
                    'is_base_unit' => $isBaseUnit,
                    'buy_price' => (int) $unit['buy_price'],
                    'sell_price' => (int) $unit['sell_price'],
                    'barcode' => trim($unit['barcode']),
                ];
            })
            ->sortByDesc('is_base_unit')
            ->values()
            ->all();
    }
}
