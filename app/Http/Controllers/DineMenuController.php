<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Setting;
use App\Services\PricingService;
use Inertia\Inertia;

class DineMenuController extends Controller
{
    public function __construct(
        private PricingService $pricingService,
    ) {}

    public function show(string $token)
    {
        $table = DiningTable::where('token', $token)
            ->where('is_active', true)
            ->with('area')
            ->firstOrFail();

        $selfOrderEnabled = Setting::getBool('dine_in_self_order_enabled', true);
        $payOnlineEnabled = Setting::getBool('dine_in_pay_online_enabled', true);

        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $products = Product::where('is_active', true)
            ->where('stock', '>', 0)
            ->with(['category', 'units.unit'])
            ->get();

        // ponytail: previewProducts() returns arrays keyed by product id (pricing fields only) — merge the effective price back onto model-shaped payloads the menu page expects (id/title/image/category_id/sell_price)
        $previews = $this->pricingService->previewProducts($products);

        $products = $products->map(function (Product $product) use ($previews) {
            $preview = $previews->get($product->id);

            return [
                'id' => $product->id,
                'title' => $product->title,
                'image' => $product->image,
                'category_id' => $product->category_id,
                'sell_price' => (int) ($preview['effective_unit_price'] ?? $product->sell_price),
            ];
        })->values();

        $storeName = Setting::get('store_name', 'Restoran');
        $storeLogo = Setting::get('store_logo');

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
            'payOnlineEnabled' => $payOnlineEnabled,
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }
}
