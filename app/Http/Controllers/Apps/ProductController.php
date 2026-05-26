<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Services\AuditLogService;
use App\Services\StockMutationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(
        private readonly StockMutationService $stockMutationService,
        private readonly AuditLogService $auditLogService
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // get products
        $products = Product::when(request()->search, function ($products) {
            $search = request()->search;

            $products = $products
                ->where('title', 'like', '%'.$search.'%')
                ->orWhere('barcode', 'like', '%'.$search.'%')
                ->orWhere('sku', 'like', '%'.$search.'%')
                ->orWhereHas('units', function ($units) use ($search) {
                    $units->where('label', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%');
                });
        })->with(['category', 'units'])->latest()->paginate(5);

        // return inertia
        return Inertia::render('Dashboard/Products/Index', [
            'products' => $products,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // get categories
        $categories = Category::all();

        // return inertia
        return Inertia::render('Dashboard/Products/Create', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $this->validatedProductData($request);
        $baseUnit = collect($validated['product_units'])->firstWhere('is_base_unit', true);

        $product = DB::transaction(function () use ($request, $validated, $baseUnit) {
            // upload image
            $image = $request->file('image');
            $image->storeAs('public/products', $image->hashName());

            // create product
            $product = Product::create([
                'image' => $image->hashName(),
                'barcode' => $baseUnit['barcode'],
                'sku' => $validated['sku'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'category_id' => $validated['category_id'],
                'buy_price' => $baseUnit['buy_price'],
                'sell_price' => $baseUnit['sell_price'],
                'stock' => $validated['stock'],
            ]);

            $this->syncProductUnits($product, $validated['product_units']);
            $this->stockMutationService->recordInitialStock($product, $request->user()?->id);

            return $product;
        });

        $this->auditLogService->log(
            event: 'product.created',
            module: 'products',
            auditable: $product,
            description: 'Produk baru dibuat.',
            after: $this->productAuditPayload($product->fresh(['units']))
        );

        // redirect
        return to_route('products.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Product $product)
    {
        // get categories
        $categories = Category::all();

        return Inertia::render('Dashboard/Products/Edit', [
            'product' => $product->load('units'),
            'categories' => $categories,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Product $product)
    {
        $before = $this->productAuditPayload($product->load('units'));
        $validated = $this->validatedProductData($request, $product);
        $baseUnit = collect($validated['product_units'])->firstWhere('is_base_unit', true);

        DB::transaction(function () use ($request, $product, $validated, $baseUnit) {
            $payload = [
                'barcode' => $baseUnit['barcode'],
                'sku' => $validated['sku'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'category_id' => $validated['category_id'],
                'buy_price' => $baseUnit['buy_price'],
                'sell_price' => $baseUnit['sell_price'],
            ];

            if ($request->file('image')) {
                // remove old image
                Storage::disk('local')->delete('public/products/'.basename($product->image));

                // upload new image
                $image = $request->file('image');
                $image->storeAs('public/products', $image->hashName());
                $payload['image'] = $image->hashName();
            }

            $product->update($payload);
            $this->syncProductUnits($product, $validated['product_units']);
        });

        $this->logProductUpdate($product, $before);

        // redirect
        return to_route('products.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // find by ID
        $product = Product::findOrFail($id);
        $before = $this->productAuditPayload($product);

        // remove image
        Storage::disk('local')->delete('public/products/'.basename($product->image));

        // delete
        $product->delete();

        $this->auditLogService->log(
            event: 'product.deleted',
            module: 'products',
            auditable: $product,
            description: 'Produk dihapus.',
            before: $before
        );

        // redirect
        return back();
    }

    private function logProductUpdate(Product $product, array $before): void
    {
        $after = $this->productAuditPayload($product->fresh(['units']));

        $this->auditLogService->log(
            event: 'product.updated',
            module: 'products',
            auditable: $product,
            description: 'Data produk diperbarui.',
            before: $before,
            after: $after
        );

        if (
            (int) $before['buy_price'] !== (int) $after['buy_price']
            || (int) $before['sell_price'] !== (int) $after['sell_price']
        ) {
            $this->auditLogService->log(
                event: 'product.price_updated',
                module: 'products',
                auditable: $product,
                description: 'Harga produk diperbarui.',
                before: [
                    'buy_price' => $before['buy_price'],
                    'sell_price' => $before['sell_price'],
                ],
                after: [
                    'buy_price' => $after['buy_price'],
                    'sell_price' => $after['sell_price'],
                ]
            );
        }
    }

    private function productAuditPayload(Product $product): array
    {
        $product->loadMissing('units');

        return [
            ...$this->auditLogService->only($product->toArray(), [
            'title',
            'barcode',
            'sku',
            'buy_price',
            'sell_price',
            'stock',
            'category_id',
            ]),
            'units' => $product->units
                ->map(fn (ProductUnit $unit) => $this->auditLogService->only($unit->toArray(), [
                    'label',
                    'conversion_qty',
                    'is_base_unit',
                    'buy_price',
                    'sell_price',
                    'barcode',
                ]))
                ->values()
                ->all(),
        ];
    }

    private function validatedProductData(Request $request, ?Product $product = null): array
    {
        $rules = [
            'image' => [$product ? 'nullable' : 'required', 'image', 'max:2048'],
            'sku' => ['required', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product?->id)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category_id' => ['required', 'exists:categories,id'],
            'stock' => [$product ? 'nullable' : 'required', 'integer', 'min:0'],
            'product_units' => ['required', 'array', 'min:1'],
            'product_units.*.id' => ['nullable', 'integer'],
            'product_units.*.label' => ['required', 'string', 'max:255'],
            'product_units.*.conversion_qty' => ['required', 'numeric', 'min:0.001'],
            'product_units.*.is_base_unit' => ['nullable', 'boolean'],
            'product_units.*.buy_price' => ['required', 'integer', 'min:0'],
            'product_units.*.sell_price' => ['required', 'integer', 'min:0'],
            'product_units.*.barcode' => ['required', 'string', 'max:255'],
        ];

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request, $product) {
            $units = collect($request->input('product_units', []));

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
        });

        $validated = $validator->validate();
        $validated['product_units'] = $this->normalizeProductUnits($validated['product_units']);

        return $validated;
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

    private function syncProductUnits(Product $product, array $units): void
    {
        $product->units()->delete();

        foreach ($units as $unit) {
            $product->units()->create($unit);
        }
    }
}
