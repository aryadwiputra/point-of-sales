<?php

declare(strict_types=1);

namespace App\Services\Transactions;

use App\Models\BankAccount;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PaymentSetting;
use App\Models\Product;
use App\Services\CashierShiftService;
use App\Services\LoyaltyService;
use App\Services\PricingService;

class TransactionIndexQueryService
{
    public function __construct(
        private readonly CashierShiftService $cashierShiftService,
        private readonly PricingService $pricingService,
        private readonly LoyaltyService $loyaltyService
    ) {}

    public function execute(int $userId): array
    {
        $activeShift = $this->cashierShiftService->getActiveShiftForUser($userId);

        $carts = Cart::with(['product', 'productUnit'])
            ->where('cashier_id', $userId)
            ->active()
            ->latest()
            ->get();

        $initialPricingPreview = $this->loyaltyService->previewCheckout(
            $this->pricingService->previewCart($carts, null)
        );

        $heldCarts = Cart::with(['product:id,title,sell_price,image', 'productUnit:id,label,sell_price'])
            ->where('cashier_id', $userId)
            ->held()
            ->get()
            ->groupBy('hold_id')
            ->map(function ($items, $holdId) {
                $first = $items->first();

                return [
                    'hold_id' => $holdId,
                    'label' => $first->hold_label,
                    'held_at' => $first->held_at?->toISOString(),
                    'items_count' => $items->sum('qty'),
                    'total' => $items->sum('price'),
                ];
            })
            ->values();

        $products = Product::with(['category:id,name', 'units'])
            ->select('id', 'barcode', 'title', 'description', 'image', 'buy_price', 'sell_price', 'stock', 'category_id')
            ->where('stock', '>', 0)
            ->orderBy('title')
            ->get();

        $pricingBadges = $this->pricingService->previewProducts($products, null);
        $products = $products->map(function (Product $product) use ($pricingBadges) {
            $pricing = $pricingBadges->get($product->id);

            return [
                ...$product->toArray(),
                'pricing_badge' => $pricing && ! empty($pricing['pricing_rule']) ? [
                    'label' => $pricing['pricing_rule']['label'],
                    'promo_price' => $pricing['pricing_rule']['price_context']
                        ? $pricing['effective_unit_price']
                        : null,
                    'base_price' => $pricing['base_unit_price'],
                    'kind' => $pricing['pricing_rule']['kind'],
                ] : null,
            ];
        });

        $paymentSetting = PaymentSetting::first();
        $defaultGateway = $paymentSetting?->default_gateway ?? 'cash';

        if (
            $defaultGateway !== 'cash'
            && (! $paymentSetting || ! $paymentSetting->isGatewayReady($defaultGateway))
        ) {
            $defaultGateway = 'cash';
        }

        return [
            'carts' => $carts,
            'carts_total' => (int) $carts->sum('price'),
            'heldCarts' => $heldCarts,
            'customers' => Customer::latest()->get(),
            'products' => $products,
            'categories' => Category::select('id', 'name', 'image')->orderBy('name')->get(),
            'initialPricingPreview' => $initialPricingPreview,
            'paymentGateways' => $paymentSetting?->enabledGateways() ?? [],
            'defaultPaymentGateway' => $defaultGateway,
            'bankAccounts' => BankAccount::active()->ordered()->get(),
            'shiftSummary' => $this->cashierShiftService->summarizeForDisplay($activeShift),
            'loyaltyTierOptions' => $this->loyaltyService->tierOptions(),
        ];
    }
}
