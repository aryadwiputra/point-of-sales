<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\PriceList;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PriceListController extends Controller
{
    public function index()
    {
        $priceLists = PriceList::withCount('items')->orderBy('priority')->get();

        return Inertia::render('Dashboard/Settings/PriceLists', [
            'priceLists' => $priceLists,
        ]);
    }

    public function show(PriceList $priceList)
    {
        $priceList->load('items.product:id,title,sku,sell_price');

        $products = Product::orderBy('title')->get(['id', 'title', 'sku', 'sell_price']);

        return Inertia::render('Dashboard/Settings/PriceListItems', [
            'priceList' => $priceList,
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'unique:price_lists,slug'],
            'customer_scope' => ['required', Rule::in(['all', 'walk_in', 'registered', 'member', 'segment'])],
            'customer_segment_id' => ['nullable', 'exists:customer_segments,id'],
            'priority' => ['integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['is_active'] = true;

        PriceList::create($validated);

        return back()->with('success', 'Price list berhasil dibuat.');
    }

    public function update(Request $request, PriceList $priceList)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', Rule::unique('price_lists', 'slug')->ignore($priceList->id)],
            'customer_scope' => ['required', Rule::in(['all', 'walk_in', 'registered', 'member', 'segment'])],
            'customer_segment_id' => ['nullable', 'exists:customer_segments,id'],
            'is_active' => ['boolean'],
            'priority' => ['integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $priceList->update($validated);

        return back()->with('success', 'Price list diperbarui.');
    }

    public function destroy(PriceList $priceList)
    {
        $priceList->delete();

        return back()->with('success', 'Price list dihapus.');
    }

    public function updateItem(Request $request, PriceList $priceList)
    {
        $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $priceList->items()->updateOrCreate(
            ['product_id' => $request->product_id],
            ['price' => $request->price]
        );

        return back()->with('success', 'Harga produk diperbarui.');
    }

    public function destroyItem(PriceList $priceList, $productId)
    {
        $priceList->items()->where('product_id', $productId)->delete();

        return back()->with('success', 'Item dihapus dari price list.');
    }
}
