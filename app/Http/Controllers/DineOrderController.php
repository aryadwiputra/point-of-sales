<?php

namespace App\Http\Controllers;

use App\Models\DineOrder;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DineOrderController extends Controller
{
    public function __construct(
        private PricingService $pricingService,
        private PaymentGatewayManager $paymentManager,
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
            'payment_option' => ['required', 'in:pay_at_counter,pay_online'],
        ]);

        $items = collect($validated['items']);
        $productIds = $items->pluck('product_id')->toArray();

        $products = Product::whereIn('id', $productIds)->get();
        $products = $this->pricingService->previewProducts($products);

        $priceMap = $products->keyBy('id')->map(fn($p) => $p->sell_price);

        $subtotal = 0;
        $orderItems = [];

        foreach ($items as $item) {
            $price = $priceMap[$item['product_id']] ?? 0;
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
        $payOnlineEnabled = Setting::getBool('dine_in_pay_online_enabled', true);

        return Inertia::render('Public/DineOrderStatus', [
            'order' => $order,
            'table' => [
                'id' => $table->id,
                'token' => $table->token,
                'name' => $table->name,
            ],
            'storeName' => $storeName,
            'payOnlineEnabled' => $payOnlineEnabled,
        ]);
    }

    public function statusCheck(string $accessToken)
    {
        $order = DineOrder::with(['items.product'])
            ->where('access_token', $accessToken)
            ->first(['id', 'status', 'payment_option', 'payment_status', 'subtotal', 'item_count', 'notes', 'created_at', 'submitted_at']);

        return response()->json(['order' => $order]);
    }

    public function webhook(Request $request)
    {
        $orderId = $request->input('order_id');
        $status = $request->input('status');
        $signatureKey = $request->input('signature_key');

        if (! $this->validateMidtransSignature($orderId, $status, $signatureKey)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $order = DineOrder::where('payment_reference', $orderId)->first();

        if (! $order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        if ($status === 'paid' || $status === 'settlement') {
            $order->update([
                'payment_status' => 'paid',
                'status' => DineOrder::STATUS_COMPLETED,
            ]);
        } elseif ($status === 'expired' || $status === 'cancel') {
            $order->update([
                'payment_status' => 'failed',
            ]);
        }

        return response()->json(['success' => true]);
    }

    private function validateMidtransSignature(string $orderId, string $status, ?string $signatureKey): bool
    {
        $serverKey = config('midtrans.server_key');

        if (empty($serverKey) || empty($signatureKey)) {
            return false;
        }

        $hashString = $orderId . $status . $serverKey;
        $expectedSignature = hash('sha512', $hashString);

        return hash_equals($expectedSignature, $signatureKey);
    }
}
