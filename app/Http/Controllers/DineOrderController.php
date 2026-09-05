<?php

namespace App\Http\Controllers;

use App\Models\DineOrder;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Setting;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DineOrderController extends Controller
{
    public function __construct(
        private PricingService $pricingService,
    ) {}

    public function store(Request $request, string $token)
    {
        $table = DiningTable::where('token', $token)->where('is_active', true)->firstOrFail();

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_id' => ['nullable', 'exists:units,id'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            // ponytail: online payment (gateway + webhook) is not wired yet — enforce counter payment only
            'payment_option' => ['required', 'in:pay_at_counter'],
        ]);

        $items = collect($validated['items']);
        $productIds = $items->pluck('product_id')->toArray();

        $productModels = Product::with('components')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $previews = $this->pricingService->previewProducts($productModels);

        $subtotal = 0;
        $orderItems = [];

        foreach ($items as $item) {
            $product = $productModels->get($item['product_id']);
            if (! $product) {
                continue;
            }

            // Server-side availability check against global stock (tables have no warehouse context).
            $available = $product->is_composite
                ? $product->compositeStock()
                : (int) $product->stock;

            if ($available < $item['qty']) {
                return back()->with('error', "Stok {$product->title} tidak mencukupi (tersedia: {$available}).");
            }

            $preview = $previews->get($product->id);
            $price = (int) ($preview['effective_unit_price'] ?? $product->sell_price);
            $subtotal += $price * $item['qty'];
            $orderItems[] = [
                'product_id' => $item['product_id'],
                'unit_id' => $item['unit_id'] ?? null,
                'qty' => $item['qty'],
                'price' => $price,
                'note' => $item['note'] ?? null,
            ];
        }

        $order = DineOrder::create([
            'dine_table_id' => $table->id,
            'access_token' => (string) Str::uuid(),
            'status' => DineOrder::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'payment_option' => $validated['payment_option'],
            'notes' => $validated['notes'] ?? null,
            'subtotal' => $subtotal,
            'item_count' => $items->sum('qty'),
            'cashier_id' => null,
        ]);

        foreach ($orderItems as $item) {
            $order->items()->create($item);
        }

        return redirect()
            ->route('dine-order.status', $order->access_token)
            ->with('success', 'Pesanan berhasil dikirim.');
    }

    public function status(string $accessToken)
    {
        $order = DineOrder::with(['table.area', 'items.product'])
            ->where('access_token', $accessToken)
            ->firstOrFail();

        $table = DiningTable::where('token', $order->table->token)
            ->where('is_active', true)
            ->firstOrFail();

        $storeName = Setting::get('store_name', 'Restoran');

        return Inertia::render('Public/DineOrderStatus', [
            'order' => $order,
            'table' => [
                'id' => $table->id,
                'token' => $table->token,
                'name' => $table->name,
            ],
            'storeName' => $storeName,
        ]);
    }

    public function statusCheck(string $accessToken)
    {
        $order = DineOrder::where('access_token', $accessToken)
            ->first(['id', 'status', 'payment_option', 'payment_status', 'subtotal', 'item_count', 'updated_at']);

        if (! $order) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        // ponytail: the URL token is the only auth factor — poll access is bounded by a 24h window and a rate limit on the route
        if ($order->updated_at->lt(now()->subHours(24))) {
            return response()->json(['message' => 'Pesanan sudah kedaluwarsa.'], 410);
        }

        return response()->json(['order' => $order]);
    }
}
