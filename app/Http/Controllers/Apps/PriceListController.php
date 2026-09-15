<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\PriceList;
use App\Models\Product;
use App\Services\OutletAccessService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PriceListController extends Controller
{
    public function __construct(private readonly OutletAccessService $outletAccess) {}

    public function index()
    {
        $outlet = $this->outletAccess->activeOutlet(request());
        $priceLists = PriceList::withCount('items')
            ->whereNull('outlet_id')->when($outlet, fn ($query) => $query->orWhere('outlet_id', $outlet->id))
            ->orderBy('priority')->get();

        return Inertia::render('Dashboard/Settings/PriceLists', [
            'priceLists' => $priceLists,
        ]);
    }

    public function show(PriceList $priceList)
    {
        abort_unless($this->canUseList($priceList), 404);
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
        $validated['outlet_id'] = $this->outletAccess->activeOutlet($request)?->id;

        PriceList::create($validated);

        return back()->with('success', 'Price list berhasil dibuat.');
    }

    public function update(Request $request, PriceList $priceList)
    {
        abort_unless($this->canUseList($priceList), 404);
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
        abort_unless($this->canUseList($priceList), 404);
        $priceList->delete();

        return back()->with('success', 'Price list dihapus.');
    }

    public function updateItem(Request $request, PriceList $priceList)
    {
        abort_unless($this->canUseList($priceList), 404);
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
        abort_unless($this->canUseList($priceList), 404);
        $priceList->items()->where('product_id', $productId)->delete();

        return back()->with('success', 'Item dihapus dari price list.');
    }

    private function canUseList(PriceList $priceList): bool
    {
        $outlet = $this->outletAccess->activeOutlet(request());

        return $priceList->outlet_id === null || ($outlet && (int) $priceList->outlet_id === (int) $outlet->id);
    }
}
