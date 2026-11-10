<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\PriceListController as AppsPriceListController;
use App\Http\Traits\ApiResponder;
use App\Models\PriceList;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PriceListController extends AppsPriceListController
{
    use ApiResponder;

    /**
     * GET /api/v1/settings/price-lists
     */
    public function listPriceLists(Request $request): JsonResponse
    {
        $outlet = $this->outletAccess->activeOutlet($request);

        $priceLists = PriceList::withCount('items')
            ->whereNull('outlet_id')
            ->when($outlet, fn ($query) => $query->orWhere('outlet_id', $outlet->id))
            ->orderBy('priority')
            ->get();

        return $this->ok($priceLists);
    }

    /**
     * GET /api/v1/settings/price-lists/{priceList}
     */
    public function showPriceList(Request $request, PriceList $priceList): JsonResponse
    {
        abort_unless($this->canUseList($priceList), 404);

        $priceList->load('items.product:id,title,sku,sell_price');

        $products = Product::orderBy('title')->get(['id', 'title', 'sku', 'sell_price']);

        return $this->ok([
            'price_list' => $priceList,
            'products' => $products,
        ]);
    }

    /**
     * POST /api/v1/settings/price-lists
     */
    public function storePriceList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'unique:price_lists,slug'],
            'customer_scope' => ['required', Rule::in(['all', 'walk_in', 'registered', 'member', 'segment'])],
            'customer_segment_id' => ['nullable', 'exists:customer_segments,id'],
            'priority' => ['integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $priceList = PriceList::create([
            ...$validated,
            'is_active' => true,
            'outlet_id' => $this->outletAccess->activeOutlet($request)?->id,
        ]);

        return $this->created($priceList);
    }

    /**
     * PUT /api/v1/settings/price-lists/{priceList}
     */
    public function updatePriceList(Request $request, PriceList $priceList): JsonResponse
    {
        abort_unless($this->canUseList($priceList), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', Rule::unique('price_lists', 'slug')->ignore($priceList->id)],
            'customer_scope' => ['required', Rule::in(['all', 'walk_in', 'registered', 'member', 'segment'])],
            'customer_segment_id' => ['nullable', 'exists:customer_segments,id'],
            'priority' => ['integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $priceList->update($validated);

        return $this->ok($priceList->fresh());
    }

    /**
     * DELETE /api/v1/settings/price-lists/{priceList}
     */
    public function destroyPriceList(PriceList $priceList): JsonResponse
    {
        abort_unless($this->canUseList($priceList), 404);

        $priceList->delete();

        return $this->noContent();
    }

    /**
     * PUT /api/v1/settings/price-lists/{priceList}/items
     */
    public function updatePriceListItem(Request $request, PriceList $priceList): JsonResponse
    {
        abort_unless($this->canUseList($priceList), 404);

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $priceList->items()->updateOrCreate(
            ['product_id' => $validated['product_id']],
            ['price' => $validated['price']],
        );

        return $this->ok($priceList->fresh()->load('items.product:id,title,sku,sell_price'));
    }

    /**
     * DELETE /api/v1/settings/price-lists/{priceList}/items/{productId}
     */
    public function destroyItem(PriceList $priceList, $productId): JsonResponse
    {
        abort_unless($this->canUseList($priceList), 404);

        $priceList->items()->where('product_id', $productId)->delete();

        return $this->noContent();
    }
}
