<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\Setting;
use App\Services\ModifierService;
use App\Services\PricingService;
use Inertia\Inertia;

class DineMenuController extends Controller
{
    public function __construct(
        private PricingService $pricingService,
        private ModifierService $modifierService,
    ) {}

    public function show(string $token)
    {
        $table = DiningTable::where('token', $token)
            ->where('is_active', true)
            ->with('area')
            ->firstOrFail();
        $outlet = $table->area?->outlet;

        $selfOrderEnabled = Setting::getBool('dine_in_self_order_enabled', true);

        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $products = Product::where('is_active', true)
            ->where(function ($query) use ($outlet) {
                $query->where('track_stock', false)
                    ->orWhere(function ($inStock) use ($outlet) {
                        $inStock->where('stock', '>', 0);
                        if ($outlet) {
                            $inStock->orWhereHas('warehouses', fn ($warehouse) => $warehouse
                                ->where('outlet_id', $outlet->id)
                                ->where('stock', '>', 0));
                        }
                    });
            })
            ->with(['category', 'units.unit'])
            ->get();

        // ponytail: previewProducts() returns arrays keyed by product id (pricing fields only) — merge the effective price back onto model-shaped payloads the menu page expects
        $previews = $this->pricingService->previewProducts($products, null, null, $outlet);

        $products = $products->map(function (Product $product) use ($previews) {
            $preview = $previews->get($product->id);
            $groups = $this->modifierService->attachableGroups($product);

            return [
                'id' => $product->id,
                'title' => $product->title,
                'image' => $product->image,
                'category_id' => $product->category_id,
                'sell_price' => (int) ($preview['effective_unit_price'] ?? $product->sell_price),
                'track_stock' => $product->tracksStock(),
                'has_modifiers' => $groups->isNotEmpty(),
                'modifier_groups' => $groups->map(fn (ModifierGroup $group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'selection_type' => $group->selection_type,
                    'min_select' => (int) $group->min_select,
                    'max_select' => $group->max_select === null ? null : (int) $group->max_select,
                    'modifiers' => $group->modifiers->map(fn (Modifier $modifier) => [
                        'id' => $modifier->id,
                        'name' => $modifier->name,
                        'price_delta' => (int) $modifier->effective_price_delta,
                        'is_default' => (bool) $modifier->is_default,
                    ])->values(),
                ])->values(),
            ];
        })->values();

        $storeName = Setting::getForOutlet('store_name', $outlet, 'Restoran');
        $storeLogo = Setting::getForOutlet('store_logo', $outlet);

        return Inertia::render('Public/DineMenu', [
            'table' => [
                'id' => $table->id,
                'token' => $table->token,
                'name' => $table->name,
                'capacity' => $table->capacity,
                'area_name' => $table->area?->name,
            ],
            'categories' => $categories,
            'products' => $products,
            'selfOrderEnabled' => $selfOrderEnabled,
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }
}
