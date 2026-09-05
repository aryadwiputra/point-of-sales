<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\AuditLogService;
use App\Services\StockMutationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
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
     * @return Response
     */
    public function index(Request $request)
    {
        $products = Product::when($request->search, function ($products, $search) {
            $products = $products->where('title', 'like', '%'.$search.'%');
        })->with('category')->latest()->paginate($this->perPage())->withQueryString();

        $warehouses = Warehouse::active()->orderBy('code')->get(['id', 'code', 'name']);

        return Inertia::render('Dashboard/Products/Index', [
            'products' => $products,
            'warehouses' => $warehouses,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        // get categories
        $categories = Category::all();

        // get non-composite products for composite component selection
        $products = Product::where('is_composite', false)
            ->orderBy('title')
            ->get(['id', 'title', 'sell_price', 'is_composite']);

        // return inertia
        return Inertia::render('Dashboard/Products/Create', [
            'categories' => $categories,
            'products' => $products,
            'units' => Unit::orderBy('code')->get(['id', 'code', 'symbol']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        /**
         * validate
         */
        $request->validate([
            'barcode' => 'required|unique:products,barcode',
            'sku' => 'required|unique:products,sku',
            'title' => 'required',
            'description' => 'required',
            'category_id' => 'required',
            'buy_price' => 'required',
            'sell_price' => 'required',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'max_stock' => 'nullable|integer|min:0',
            ...$this->compositeRules(),
            ...$this->unitRules(),
        ]);
        // upload image
        $image = $request->file('image');
        $image->storeAs('public/products', $image->hashName());

        // create product
        $product = Product::create([
            'image' => $image->hashName(),
            'barcode' => $request->barcode,
            'sku' => $request->sku,
            'title' => $request->title,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'buy_price' => $request->buy_price,
            'sell_price' => $request->sell_price,
            'stock' => $request->stock,
            'min_stock' => $request->min_stock ?? 0,
            'max_stock' => $request->max_stock ?? 0,
            'is_composite' => $request->boolean('is_composite'),
        ]);

        if ($request->boolean('is_composite')) {
            $this->validateComponentProducts($request);
            $this->syncComponents($product, $request->input('components'));
        } else {
            $this->syncUnits($product, $request->input('units'));
            $this->stockMutationService->recordInitialStock($product, $request->user()?->id);
        }
        $this->auditLogService->log(
            event: 'product.created',
            module: 'products',
            auditable: $product,
            description: 'Produk baru dibuat.',
            after: $this->productAuditPayload($product->fresh())
        );

        // redirect
        return to_route('products.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Product $product)
    {
        // get categories
        $categories = Category::all();

        // get non-composite products for composite component selection
        $products = Product::where('is_composite', false)
            ->where('id', '!=', $product->id)
            ->orderBy('title')
            ->get(['id', 'title', 'sell_price', 'is_composite']);

        return Inertia::render('Dashboard/Products/Edit', [
            'product' => $product->load('components', 'units'),
            'categories' => $categories,
            'products' => $products,
            'units' => Unit::orderBy('code')->get(['id', 'code', 'symbol']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, Product $product)
    {
        $before = $this->productAuditPayload($product);

        /**
         * validate
         */
        $request->validate([
            'barcode' => 'required|unique:products,barcode,'.$product->id,
            'sku' => 'required|unique:products,sku,'.$product->id,
            'title' => 'required',
            'description' => 'required',
            'category_id' => 'required',
            'buy_price' => 'required',
            'sell_price' => 'required',
            'min_stock' => 'nullable|integer|min:0',
            'max_stock' => 'nullable|integer|min:0',
            ...$this->compositeRules(),
            ...$this->unitRules(),
        ]);

        if ($request->boolean('is_composite')) {
            $this->validateComponentProducts($request, $product);
        } elseif ($product->is_composite && $product->components()->exists()) {
            return back()->withErrors([
                'components' => 'Produk komposit harus memiliki minimal satu komponen.',
            ]);
        }

        // check image update
        if ($request->file('image')) {

            // remove old image
            Storage::disk('local')->delete('public/products/'.basename($product->image));

            // upload new image
            $image = $request->file('image');
            $image->storeAs('public/products', $image->hashName());

            // update product with new image
            $product->update([
                'image' => $image->hashName(),
                'barcode' => $request->barcode,
                'sku' => $request->sku,
                'title' => $request->title,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'buy_price' => $request->buy_price,
                'sell_price' => $request->sell_price,
                'is_composite' => $request->boolean('is_composite'),
            ]);

            if ($request->boolean('is_composite')) {
                $this->syncComponents($product, $request->input('components'));
            } else {
                $this->syncUnits($product, $request->input('units'));
            }

            $this->logProductUpdate($product, $before);

            return to_route('products.index');
        }

        // update product without image
        $product->update([
            'barcode' => $request->barcode,
            'sku' => $request->sku,
            'title' => $request->title,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'buy_price' => $request->buy_price,
            'sell_price' => $request->sell_price,
            'is_composite' => $request->boolean('is_composite'),
        ]);

        if ($request->boolean('is_composite')) {
            $this->syncComponents($product, $request->input('components'));
        } else {
            $this->syncUnits($product, $request->input('units'));
        }

        $this->logProductUpdate($product, $before);

        // redirect
        return to_route('products.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
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

    private function compositeRules(): array
    {
        return [
            'is_composite' => 'nullable|boolean',
            'components' => 'required_if:is_composite,1,true|array|min:1',
            'components.*.component_product_id' => 'required|integer|distinct|exists:products,id',
            'components.*.qty' => 'required|integer|min:1',
        ];
    }

    private function validateComponentProducts(Request $request, ?Product $product = null): void
    {
        $componentIds = collect($request->input('components'))
            ->pluck('component_product_id')
            ->unique();

        if ($product && $componentIds->contains($product->id)) {
            abort(422, 'Produk tidak bisa menjadi komponen dirinya sendiri.');
        }

        $compositeCount = Product::whereIn('id', $componentIds)->where('is_composite', true)->count();

        if ($compositeCount > 0) {
            abort(422, 'Komponen tidak boleh produk komposit lain.');
        }
    }

    private function syncComponents(Product $product, ?array $components): void
    {
        $product->components()->sync(
            collect($components)->mapWithKeys(fn (array $c) => [
                $c['component_product_id'] => ['qty' => (int) $c['qty']],
            ])
        );
    }

    private function unitRules(): array
    {
        return [
            'units' => 'nullable|array',
            'units.*.unit_id' => 'required|integer|distinct|exists:units,id',
            'units.*.is_base' => 'required|boolean',
            'units.*.conversion_factor' => 'required|numeric|min:0.0001',
            'units.*.buy_price' => 'nullable|integer|min:0',
            'units.*.sell_price' => 'nullable|integer|min:0',
            'units.*.barcode' => 'nullable|string|max:255',
        ];
    }

    private function syncUnits(Product $product, ?array $units): void
    {
        $rows = collect($units ?? [])
            ->keyBy('unit_id')
            ->map(fn (array $u) => [
                'is_base' => $u['is_base'],
                'conversion_factor' => $u['conversion_factor'],
                'buy_price' => $u['buy_price'] ?? $product->buy_price,
                'sell_price' => $u['sell_price'] ?? $product->sell_price,
                'barcode' => $u['barcode'] ?? null,
            ]);

        if ($rows->isEmpty()) {
            // ponytail: no units sent — only seed default PCS base when product has none (matches docs/features/unit-conversion.md)
            if ($product->units()->exists()) {
                return;
            }

            $pcs = Unit::where('code', 'PCS')->first();
            if ($pcs) {
                $product->units()->attach($pcs->id, [
                    'is_base' => true,
                    'conversion_factor' => 1,
                    'buy_price' => $product->buy_price,
                    'sell_price' => $product->sell_price,
                ]);
            }

            return;
        }

        if (! $rows->contains(fn ($r) => $r['is_base'])) {
            // ponytail: no explicit base unit sent — treat first row as base with factor 1
            $first = $rows->keys()->first();
            $rows[$first] = ['is_base' => true, 'conversion_factor' => 1] + $rows[$first];
        }

        $product->units()->sync($rows->all());
    }

    private function logProductUpdate(Product $product, array $before): void
    {
        $after = $this->productAuditPayload($product->fresh());

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
        return $this->auditLogService->only($product->toArray(), [
            'title',
            'barcode',
            'sku',
            'buy_price',
            'sell_price',
            'stock',
            'category_id',
        ]);
    }
}
