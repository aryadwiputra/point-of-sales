<?php

declare(strict_types=1);

namespace App\Services\PricingRules;

use App\Models\Category;
use App\Models\PricingRule;
use App\Models\PricingRuleBundleItem;
use App\Models\PricingRuleBuyGetItem;
use App\Models\PricingRuleQtyBreak;
use App\Models\Product;
use App\Services\LoyaltyService;

class PricingRulePayloadService
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService
    ) {}

    public function formPayload(): array
    {
        return [
            'products' => Product::orderBy('title')->get(['id', 'title', 'sell_price', 'category_id']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'tierOptions' => $this->loyaltyService->tierOptions(),
            'kindOptions' => [
                ['value' => PricingRule::KIND_STANDARD_DISCOUNT, 'label' => 'Diskon Standar'],
                ['value' => PricingRule::KIND_QTY_BREAK, 'label' => 'Harga Grosir / Qty Break'],
                ['value' => PricingRule::KIND_BUNDLE_PRICE, 'label' => 'Bundle Price'],
                ['value' => PricingRule::KIND_BUY_X_GET_Y, 'label' => 'Buy X Get Y'],
            ],
        ];
    }

    public function editRulePayload(PricingRule $pricingRule): array
    {
        $pricingRule->load(['qtyBreaks', 'bundleItems', 'buyGetItems']);

        return [
            ...$pricingRule->toArray(),
            'qty_breaks' => $pricingRule->qtyBreaks->map(fn (PricingRuleQtyBreak $break) => [
                'id' => $break->id,
                'min_qty' => (int) $break->min_qty,
                'discount_type' => $break->discount_type,
                'discount_value' => (float) $break->discount_value,
                'sort_order' => (int) $break->sort_order,
            ])->values()->all(),
            'bundle_items' => $pricingRule->bundleItems->map(fn (PricingRuleBundleItem $item) => [
                'id' => $item->id,
                'product_id' => (int) $item->product_id,
                'quantity' => (int) $item->quantity,
                'sort_order' => (int) $item->sort_order,
            ])->values()->all(),
            'buy_get_items' => $pricingRule->buyGetItems->map(fn (PricingRuleBuyGetItem $item) => [
                'id' => $item->id,
                'product_id' => (int) $item->product_id,
                'role' => $item->role,
                'quantity' => (int) $item->quantity,
                'sort_order' => (int) $item->sort_order,
            ])->values()->all(),
        ];
    }

    public function auditPayload(PricingRule $rule): array
    {
        return [
            'name' => $rule->name,
            'kind' => $rule->kind,
            'is_active' => (bool) $rule->is_active,
            'priority' => (int) $rule->priority,
            'target_type' => $rule->target_type,
            'product_id' => $rule->product_id,
            'category_id' => $rule->category_id,
            'customer_scope' => $rule->customer_scope,
            'eligible_loyalty_tiers' => $rule->eligible_loyalty_tiers,
            'discount_type' => $rule->discount_type,
            'discount_value' => (float) $rule->discount_value,
            'preview_quantity_multiplier' => (int) $rule->preview_quantity_multiplier,
            'starts_at' => optional($rule->starts_at)?->toIso8601String(),
            'ends_at' => optional($rule->ends_at)?->toIso8601String(),
            'notes' => $rule->notes,
            'qty_breaks' => $rule->qtyBreaks->map->only(['min_qty', 'discount_type', 'discount_value', 'sort_order'])->values()->all(),
            'bundle_items' => $rule->bundleItems->map->only(['product_id', 'quantity', 'sort_order'])->values()->all(),
            'buy_get_items' => $rule->buyGetItems->map->only(['product_id', 'role', 'quantity', 'sort_order'])->values()->all(),
        ];
    }
}
