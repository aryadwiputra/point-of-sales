<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\IndexProductRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use App\Services\Products\CreateProductService;
use App\Services\Products\DeleteProductService;
use App\Services\Products\ProductIndexQueryService;
use App\Services\Products\ProductPayloadService;
use App\Services\Products\UpdateProductService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(IndexProductRequest $request, ProductIndexQueryService $service): Response
    {
        return Inertia::render(
            'Dashboard/Products/Index',
            $service->execute($request->input('search'))
        );
    }

    public function create(ProductPayloadService $service): Response
    {
        return Inertia::render('Dashboard/Products/Create', $service->formPayload());
    }

    public function store(StoreProductRequest $request, CreateProductService $service): RedirectResponse
    {
        $service->execute($request->normalizedData(), $request->file('image'), $request->user()?->id);

        return to_route('products.index');
    }

    public function edit(Product $product, ProductPayloadService $service): Response
    {
        return Inertia::render('Dashboard/Products/Edit', [
            'product' => $product->load('units'),
            ...$service->formPayload(),
        ]);
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
        UpdateProductService $service
    ): RedirectResponse {
        $service->execute($product, $request->normalizedData(), $request->file('image'));

        return to_route('products.index');
    }

    public function destroy(Product $product, DeleteProductService $service): RedirectResponse
    {
        $service->execute($product);

        return back();
    }
}
