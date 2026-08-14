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

        $products = $this->pricingService->previewProducts($products);

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
