<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => [$this->imageRuleRequirement(), 'image', 'max:2048'],
            'sku' => ['required', 'string', 'max:255', Rule::unique('products', 'sku')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category_id' => ['required', 'exists:categories,id'],
            'stock' => ['required', 'integer', 'min:0'],
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

    private function imageRuleRequirement(): string
    {
        return Setting::productDisplayMode() === Setting::PRODUCT_DISPLAY_COMPACT_LIST
            ? 'nullable'
            : 'required';
    }

    private function validateUnits($validator): void
    {
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
            $isBaseUnit = filter_var($unit['is_base_unit'] ?? false, FILTER_VALIDATE_BOOL);

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

            if (ProductUnit::where('barcode', $barcode)->exists()) {
                $validator->errors()->add("product_units.{$index}.barcode", 'Barcode satuan sudah digunakan.');
            }

            if (Product::where('barcode', $barcode)->exists()) {
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
