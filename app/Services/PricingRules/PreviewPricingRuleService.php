<?php

declare(strict_types=1);

namespace App\Services\PricingRules;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\PricingRule;
use App\Models\PricingRuleBundleItem;
use App\Models\PricingRuleBuyGetItem;
use App\Models\PricingRuleQtyBreak;
use App\Models\Product;
use App\Services\PricingService;
use Illuminate\Support\Collection;

class PreviewPricingRuleService
{
    public function __construct(
        private readonly PricingService $pricingService
    ) {}

    public function execute(array $payload, ?int $previewCustomerId): array
    {
        $rule = new PricingRule($payload['rule']);
        $rule->setRelation(
            'qtyBreaks',
            collect($payload['relations']['qty_breaks'])->map(fn (array $break) => new PricingRuleQtyBreak($break))
        );
        $rule->setRelation(
            'bundleItems',
            collect($payload['relations']['bundle_items'])->map(fn (array $item) => new PricingRuleBundleItem($item))
        );
        $rule->setRelation(
            'buyGetItems',
            collect($payload['relations']['buy_get_items'])->map(fn (array $item) => new PricingRuleBuyGetItem($item))
        );

        $customer = $previewCustomerId
            ? Customer::find($previewCustomerId)
            : null;

        return $this->pricingService->previewCartWithRules(
            $this->buildPreviewCarts($rule),
            $customer,
            collect([$rule])
        );
    }

    private function buildPreviewCarts(PricingRule $rule): Collection
    {
        if ($rule->kind === PricingRule::KIND_BUNDLE_PRICE) {
            $products = Product::query()
                ->whereIn('id', $rule->bundleItems->pluck('product_id'))
                ->get();
        } elseif ($rule->kind === PricingRule::KIND_BUY_X_GET_Y) {
            $products = Product::query()
                ->whereIn('id', $rule->buyGetItems->pluck('product_id'))
                ->get();
        } else {
            $products = match ($rule->target_type) {
                PricingRule::TARGET_PRODUCT => Product::query()
                    ->whereKey($rule->product_id)
                    ->get(),
                PricingRule::TARGET_CATEGORY => Product::query()
                    ->where('category_id', $rule->category_id)
                    ->orderBy('title')
                    ->limit(3)
                    ->get(),
                default => Product::query()->orderBy('title')->limit(3)->get(),
            };
        }

        return $products
            ->values()
            ->map(function (Product $product, int $index) use ($rule) {
                $qty = $this->previewQuantityForRule($rule, $product);
                $cart = new Cart([
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'price' => (int) $product->sell_price * $qty,
                ]);
                $cart->id = -($index + 1);
                $cart->setRelation('product', $product);

                return $cart;
            });
    }

    private function previewQuantityForRule(PricingRule $rule, Product $product): int
    {
        if ($rule->kind === PricingRule::KIND_QTY_BREAK) {
            return max(1, (int) ($rule->preview_quantity_multiplier ?: $rule->qtyBreaks->max('min_qty') ?: 1));
        }

        if ($rule->kind === PricingRule::KIND_BUNDLE_PRICE) {
            return (int) ($rule->bundleItems->firstWhere('product_id', $product->id)?->quantity ?? 1);
        }

        if ($rule->kind === PricingRule::KIND_BUY_X_GET_Y) {
            return (int) ($rule->buyGetItems->where('product_id', $product->id)->sum('quantity') ?: 1);
        }

        return max(1, (int) ($rule->preview_quantity_multiplier ?: 1));
    }
}
