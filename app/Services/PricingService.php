<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\PricingRule;
use App\Models\PricingRuleBuyGetItem;
use App\Models\PricingRuleQtyBreak;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PricingService
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService
    ) {}

    public function getActiveRules(?CarbonInterface $at = null): Collection
    {
        $at = $at ?? now();

        return PricingRule::query()
            ->with([
                'product:id,title,sell_price,category_id',
                'category:id,name',
                'qtyBreaks',
                'bundleItems.product:id,title,sell_price,category_id',
                'buyGetItems.product:id,title,sell_price,category_id',
            ])
            ->where('is_active', true)
            ->where(function ($query) use ($at) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function ($query) use ($at) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            })
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }

    public function previewCart(iterable $carts, ?Customer $customer = null, ?CarbonInterface $at = null): array
    {
        $cartCollection = collect($carts)
            ->filter(fn ($cart) => $cart instanceof Cart && $cart->product)
            ->values();
        $rules = $this->getActiveRules($at);

        return $this->buildPreview($cartCollection, $customer, $rules);
    }

    public function previewCartWithRules(iterable $carts, ?Customer $customer, Collection $rules): array
    {
        $cartCollection = collect($carts)
            ->filter(fn ($cart) => $cart instanceof Cart && $cart->product)
            ->values();

        return $this->buildPreview($cartCollection, $customer, $rules->values());
    }

    public function previewProducts(iterable $products, ?Customer $customer = null, ?CarbonInterface $at = null): Collection
    {
        $rules = $this->getActiveRules($at);

        return collect($products)
            ->filter(fn ($product) => $product instanceof Product)
            ->mapWithKeys(function (Product $product) use ($customer, $rules) {
                return [$product->id => $this->calculateProductPrice($product, 1, $customer, $rules)];
            });
    }

    public function calculateProductPrice(
        Product $product,
        int $qty = 1,
        ?Customer $customer = null,
        ?Collection $rules = null
    ): array {
        $rules = $rules ?? $this->getActiveRules();
        $quantity = max(1, $qty);
        $matchingRules = $rules
            ->filter(fn (PricingRule $rule) => $this->matchesCustomerScope($rule, $customer))
            ->filter(fn (PricingRule $rule) => $this->ruleTouchesProduct($rule, $product));

        $directCandidates = $matchingRules
            ->filter(fn (PricingRule $rule) => in_array($rule->kind, [
                PricingRule::KIND_STANDARD_DISCOUNT,
                PricingRule::KIND_QTY_BREAK,
            ], true))
            ->map(function (PricingRule $rule) use ($product, $quantity) {
                $previewQuantity = $rule->kind === PricingRule::KIND_QTY_BREAK
                    ? max($quantity, (int) ($rule->preview_quantity_multiplier ?: $rule->qtyBreaks->max('min_qty') ?: 1))
                    : $quantity;

                return $this->calculateLineCandidate($rule, $product, $previewQuantity);
            })
            ->filter()
            ->sortBy([
                ['rule.priority', 'desc'],
                ['line_discount', 'desc'],
                ['rule.id', 'asc'],
            ])
            ->first();

        if ($directCandidates) {
            $baseUnitPrice = (int) $directCandidates['base_unit_price'];
            $effectiveUnitPrice = (int) round(
                $directCandidates['line_total'] / max(1, (int) $directCandidates['quantity'])
            );
            $rule = $directCandidates['rule'];

            return [
                'base_unit_price' => $baseUnitPrice,
                'effective_unit_price' => $effectiveUnitPrice,
                'quantity' => (int) $directCandidates['quantity'],
                'line_base_total' => (int) $directCandidates['line_base_total'],
                'line_total' => (int) $directCandidates['line_total'],
                'line_discount_total' => (int) $directCandidates['line_discount'],
                'pricing_rule' => $this->serializeRule($rule),
            ];
        }

        $complexRule = $matchingRules
            ->filter(fn (PricingRule $rule) => in_array($rule->kind, [
                PricingRule::KIND_BUNDLE_PRICE,
                PricingRule::KIND_BUY_X_GET_Y,
            ], true))
            ->sortBy([
                ['priority', 'desc'],
                ['id', 'asc'],
            ])
            ->first();

        return [
            'base_unit_price' => (int) $product->sell_price,
            'effective_unit_price' => (int) $product->sell_price,
            'quantity' => $quantity,
            'line_base_total' => (int) $product->sell_price * $quantity,
            'line_total' => (int) $product->sell_price * $quantity,
            'line_discount_total' => 0,
            'pricing_rule' => $complexRule ? $this->serializeRule($complexRule, false) : null,
        ];
    }

    public function ruleLabel(PricingRule $rule): string
    {
        return match ($rule->kind) {
            PricingRule::KIND_QTY_BREAK => 'Grosir '.$this->standardDiscountLabel($rule),
            PricingRule::KIND_BUNDLE_PRICE => 'Bundle Rp '.number_format((float) $rule->discount_value, 0, ',', '.'),
            PricingRule::KIND_BUY_X_GET_Y => 'Buy X Get Y',
            default => $this->standardDiscountLabel($rule),
        };
    }

    private function buildPreview(Collection $carts, ?Customer $customer, Collection $rules): array
    {
        $items = $carts->map(function (Cart $cart) {
            $quantity = (float) $cart->qty;
            $baseUnitPrice = (int) ($cart->productUnit?->sell_price ?? $cart->product->sell_price);
            $lineBaseTotal = (int) ($cart->price ?: round($baseUnitPrice * $quantity));

            return [
                'cart_id' => $cart->id,
                'product_id' => $cart->product_id,
                'product_title' => $cart->product?->title,
                'qty' => $quantity,
                'base_unit_price' => $baseUnitPrice,
                'effective_unit_price' => $baseUnitPrice,
                'line_base_total' => $lineBaseTotal,
                'line_total' => $lineBaseTotal,
                'line_discount_total' => 0,
                'pricing_rule' => null,
                'pricing_group_key' => null,
                'pricing_group_label' => null,
                'applied_rules' => [],
            ];
        })->keyBy('cart_id');

        $remainingQuantities = $items
            ->mapWithKeys(fn (array $item, int|string $cartId) => [(int) $cartId => (int) floor((float) $item['qty'])])
            ->all();

        $eligibleRules = $rules
            ->filter(fn (PricingRule $rule) => $this->matchesCustomerScope($rule, $customer))
            ->values();

        $appliedGroups = [];

        $bundleRules = $eligibleRules
            ->filter(fn (PricingRule $rule) => $rule->kind === PricingRule::KIND_BUNDLE_PRICE)
            ->values();
        $appliedGroups = array_merge(
            $appliedGroups,
            $this->applyComplexStage($bundleRules, $items, $remainingQuantities, 'bundle')
        );

        $buyGetRules = $eligibleRules
            ->filter(fn (PricingRule $rule) => $rule->kind === PricingRule::KIND_BUY_X_GET_Y)
            ->values();
        $appliedGroups = array_merge(
            $appliedGroups,
            $this->applyComplexStage($buyGetRules, $items, $remainingQuantities, 'buy_get')
        );

        foreach ($items as $cartId => $item) {
            $remainingQty = max(0, (int) ($remainingQuantities[$cartId] ?? 0));
            if ($remainingQty === 0) {
                continue;
            }

            $cartProduct = $carts->firstWhere('id', $cartId)?->product;
            if (! $cartProduct) {
                continue;
            }

            $candidate = $eligibleRules
                ->filter(fn (PricingRule $rule) => in_array($rule->kind, [
                    PricingRule::KIND_QTY_BREAK,
                    PricingRule::KIND_STANDARD_DISCOUNT,
                ], true))
                ->map(fn (PricingRule $rule) => $this->calculateLineCandidate($rule, $cartProduct, $remainingQty))
                ->filter()
                ->sortBy([
                    ['rule.priority', 'desc'],
                    ['line_discount', 'desc'],
                    ['rule.id', 'asc'],
                ])
                ->first();

            if (! $candidate || (int) $candidate['line_discount'] <= 0) {
                continue;
            }

            $currentItem = $items->get($cartId);
            $currentItem['line_total'] -= (int) $candidate['line_discount'];
            $currentItem['line_discount_total'] += (int) $candidate['line_discount'];
            $currentItem['pricing_rule'] = $this->serializeRule($candidate['rule']);
            $currentItem['applied_rules'][] = $this->serializeRule($candidate['rule']);
            $currentItem['pricing_group_key'] ??= 'rule-'.$candidate['rule']->id;
            $currentItem['pricing_group_label'] ??= $candidate['rule']->name;
            $items->put($cartId, $currentItem);
        }

        $items = $items->map(function (array $item) {
            $lineTotal = max(0, (int) $item['line_total']);
            $item['line_total'] = $lineTotal;
            $item['line_discount_total'] = max(0, (int) $item['line_discount_total']);
            $item['effective_unit_price'] = (int) round($lineTotal / max(0.001, (float) $item['qty']));

            return $item;
        })->values();

        $baseSubtotal = (int) $items->sum('line_base_total');
        $promoDiscountTotal = (int) $items->sum('line_discount_total');
        $subtotalAfterPromo = max(0, $baseSubtotal - $promoDiscountTotal);

        return [
            'items' => $items->all(),
            'applied_groups' => array_values($appliedGroups),
            'consumed_quantities' => collect($remainingQuantities)
                ->mapWithKeys(function (int $qty, int $cartId) use ($items) {
                    $original = (int) floor((float) collect($items)->firstWhere('cart_id', $cartId)['qty']);

                    return [$cartId => max(0, $original - $qty)];
                })
                ->all(),
            'unmatched_items' => collect($remainingQuantities)
                ->filter(fn (int $qty) => $qty > 0)
                ->mapWithKeys(fn (int $qty, int $cartId) => [$cartId => $qty])
                ->all(),
            'summary' => [
                'base_subtotal' => $baseSubtotal,
                'promo_discount_total' => $promoDiscountTotal,
                'subtotal_after_promo' => $subtotalAfterPromo,
            ],
        ];
    }

    private function applyComplexStage(
        Collection $rules,
        Collection &$items,
        array &$remainingQuantities,
        string $stage
    ): array {
        $groups = [];

        while (true) {
            $candidates = $rules
                ->map(function (PricingRule $rule) use ($items, $remainingQuantities, $stage) {
                    return $stage === 'bundle'
                        ? $this->buildBundleCandidate($rule, $items, $remainingQuantities)
                        : $this->buildBuyGetCandidate($rule, $items, $remainingQuantities);
                })
                ->filter()
                ->sortBy([
                    ['priority', 'desc'],
                    ['discount_total', 'desc'],
                    ['rule_id', 'asc'],
                ])
                ->values();

            $candidate = $candidates->first();
            if (! $candidate) {
                break;
            }

            foreach ($candidate['participants'] as $participant) {
                $cartId = (int) $participant['cart_id'];
                $consumeQty = (int) $participant['quantity'];
                $remainingQuantities[$cartId] = max(0, (int) ($remainingQuantities[$cartId] ?? 0) - $consumeQty);

                $currentItem = $items->get($cartId);
                $currentItem['line_total'] -= (int) $participant['discount_total'];
                $currentItem['line_discount_total'] += (int) $participant['discount_total'];
                $currentItem['pricing_group_key'] = $candidate['group_key'];
                $currentItem['pricing_group_label'] = $candidate['group_label'];
                $currentItem['pricing_rule'] = $this->serializeRule($candidate['rule']);
                $currentItem['applied_rules'][] = $this->serializeRule($candidate['rule']);
                $items->put($cartId, $currentItem);
            }

            $groups[] = [
                'key' => $candidate['group_key'],
                'label' => $candidate['group_label'],
                'rule' => $this->serializeRule($candidate['rule']),
                'discount_total' => (int) $candidate['discount_total'],
                'participants' => $candidate['participants'],
            ];
        }

        return $groups;
    }

    private function buildBundleCandidate(PricingRule $rule, Collection $items, array $remainingQuantities): ?array
    {
        if ($rule->bundleItems->isEmpty()) {
            return null;
        }

        $participants = [];
        $baseTotal = 0;
        $tempRemaining = $remainingQuantities;

        foreach ($rule->bundleItems as $bundleItem) {
            $matched = $this->consumeMatchingItems(
                $items,
                $tempRemaining,
                fn (array $item) => (int) $item['product_id'] === (int) $bundleItem->product_id,
                (int) $bundleItem->quantity
            );

            if ($matched === null) {
                return null;
            }

            $participants = array_merge($participants, $matched);
        }

        foreach ($participants as $participant) {
            $baseTotal += (int) $participant['base_total'];
        }

        $bundlePrice = (int) round((float) $rule->discount_value);
        if ($bundlePrice >= $baseTotal) {
            return null;
        }

        $allocations = $this->allocateDiscount(
            $participants,
            $baseTotal - $bundlePrice
        );

        return [
            'rule' => $rule,
            'rule_id' => (int) $rule->id,
            'priority' => (int) $rule->priority,
            'group_key' => 'bundle-'.$rule->id.'-'.str()->uuid(),
            'group_label' => $rule->name,
            'discount_total' => $baseTotal - $bundlePrice,
            'participants' => $allocations,
        ];
    }

    private function buildBuyGetCandidate(PricingRule $rule, Collection $items, array $remainingQuantities): ?array
    {
        $buyItems = $rule->buyGetItems
            ->where('role', PricingRuleBuyGetItem::ROLE_BUY)
            ->values();
        $getItems = $rule->buyGetItems
            ->where('role', PricingRuleBuyGetItem::ROLE_GET)
            ->values();

        if ($buyItems->isEmpty() || $getItems->isEmpty()) {
            return null;
        }

        $participants = [];
        $tempRemaining = $remainingQuantities;

        foreach ($buyItems as $buyItem) {
            $matched = $this->consumeMatchingItems(
                $items,
                $tempRemaining,
                fn (array $item) => (int) $item['product_id'] === (int) $buyItem->product_id,
                (int) $buyItem->quantity
            );

            if ($matched === null) {
                return null;
            }

            foreach ($matched as $match) {
                $match['discount_total'] = 0;
                $participants[] = $match;
            }
        }

        $rewardParticipants = [];
        foreach ($getItems as $getItem) {
            $matched = $this->consumeMatchingItems(
                $items,
                $tempRemaining,
                fn (array $item) => (int) $item['product_id'] === (int) $getItem->product_id,
                (int) $getItem->quantity
            );

            if ($matched === null) {
                return null;
            }

            foreach ($matched as $match) {
                $match['discount_total'] = (int) $match['base_total'];
                $rewardParticipants[] = $match;
                $participants[] = $match;
            }
        }

        $discountTotal = (int) collect($rewardParticipants)->sum('discount_total');
        if ($discountTotal <= 0) {
            return null;
        }

        return [
            'rule' => $rule,
            'rule_id' => (int) $rule->id,
            'priority' => (int) $rule->priority,
            'group_key' => 'bxgy-'.$rule->id.'-'.str()->uuid(),
            'group_label' => $rule->name,
            'discount_total' => $discountTotal,
            'participants' => $participants,
        ];
    }

    private function consumeMatchingItems(
        Collection $items,
        array &$remainingQuantities,
        callable $matcher,
        int $requiredQuantity
    ): ?array {
        $required = max(1, $requiredQuantity);
        $matches = [];

        foreach ($items as $cartId => $item) {
            if ($required <= 0) {
                break;
            }

            if (! $matcher($item)) {
                continue;
            }

            $availableQty = (int) ($remainingQuantities[$cartId] ?? 0);
            if ($availableQty <= 0) {
                continue;
            }

            $take = min($availableQty, $required);
            $matches[] = [
                'cart_id' => (int) $cartId,
                'product_id' => (int) $item['product_id'],
                'product_title' => $item['product_title'],
                'quantity' => $take,
                'base_total' => (int) $item['base_unit_price'] * $take,
            ];
            $required -= $take;
        }

        return $required === 0 ? $matches : null;
    }

    private function allocateDiscount(array $participants, int $discountTotal): array
    {
        $baseTotal = max(1, (int) collect($participants)->sum('base_total'));
        $allocated = [];
        $running = 0;
        $lastIndex = array_key_last($participants);

        foreach ($participants as $index => $participant) {
            $share = $index === $lastIndex
                ? $discountTotal - $running
                : (int) floor($discountTotal * ((int) $participant['base_total'] / $baseTotal));
            $share = max(0, min((int) $participant['base_total'], $share));
            $running += $share;
            $participant['discount_total'] = $share;
            $allocated[] = $participant;
        }

        return $allocated;
    }

    private function calculateLineCandidate(PricingRule $rule, Product $product, int $quantity): ?array
    {
        if (! $this->matchesTarget($rule, $product)) {
            return null;
        }

        $baseUnitPrice = (int) $product->sell_price;
        $lineBaseTotal = $baseUnitPrice * $quantity;

        if ($rule->kind === PricingRule::KIND_QTY_BREAK) {
            $break = $rule->qtyBreaks
                ->filter(fn (PricingRuleQtyBreak $break) => $quantity >= (int) $break->min_qty)
                ->sortBy([
                    ['min_qty', 'desc'],
                    ['sort_order', 'asc'],
                    ['id', 'asc'],
                ])
                ->first();

            if (! $break) {
                return null;
            }

            $discount = $this->resolveLineDiscount(
                $break->discount_type,
                (float) $break->discount_value,
                $baseUnitPrice,
                $quantity
            );

            return [
                'rule' => $rule,
                'quantity' => $quantity,
                'base_unit_price' => $baseUnitPrice,
                'line_base_total' => $lineBaseTotal,
                'line_total' => max(0, $lineBaseTotal - $discount),
                'line_discount' => $discount,
            ];
        }

        $discount = $this->resolveLineDiscount(
            $rule->discount_type,
            (float) $rule->discount_value,
            $baseUnitPrice,
            $quantity
        );

        return [
            'rule' => $rule,
            'quantity' => $quantity,
            'base_unit_price' => $baseUnitPrice,
            'line_base_total' => $lineBaseTotal,
            'line_total' => max(0, $lineBaseTotal - $discount),
            'line_discount' => $discount,
        ];
    }

    private function matchesCustomerScope(PricingRule $rule, ?Customer $customer): bool
    {
        return match ($rule->customer_scope) {
            PricingRule::SCOPE_ALL => true,
            PricingRule::SCOPE_WALK_IN => $customer === null,
            PricingRule::SCOPE_REGISTERED => $customer !== null,
            PricingRule::SCOPE_MEMBER => $this->matchesMemberRule($rule, $customer),
            default => false,
        };
    }

    private function matchesMemberRule(PricingRule $rule, ?Customer $customer): bool
    {
        if (! $customer || ! $customer->is_loyalty_member) {
            return false;
        }

        $eligibleTiers = collect($rule->eligible_loyalty_tiers ?? [])
            ->filter()
            ->values();

        if ($eligibleTiers->isEmpty()) {
            return true;
        }

        return $eligibleTiers->contains($customer->loyalty_tier);
    }

    private function matchesTarget(PricingRule $rule, Product $product): bool
    {
        return match ($rule->target_type) {
            PricingRule::TARGET_ALL => true,
            PricingRule::TARGET_PRODUCT => (int) $rule->product_id === (int) $product->id,
            PricingRule::TARGET_CATEGORY => (int) $rule->category_id === (int) $product->category_id,
            default => false,
        };
    }

    private function resolveLineDiscount(
        string $discountType,
        float $discountValue,
        int $baseUnitPrice,
        int $quantity
    ): int {
        $lineBaseTotal = $baseUnitPrice * $quantity;

        $discount = match ($discountType) {
            PricingRule::TYPE_PERCENTAGE => (int) round($lineBaseTotal * ($discountValue / 100)),
            PricingRule::TYPE_FIXED_AMOUNT => (int) round($discountValue) * $quantity,
            PricingRule::TYPE_FIXED_PRICE => max(0, $lineBaseTotal - ((int) round($discountValue) * $quantity)),
            default => 0,
        };

        return min($lineBaseTotal, max(0, $discount));
    }

    private function serializeRule(PricingRule $rule, bool $includePriceContext = true): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'kind' => $rule->kind,
            'label' => $this->ruleLabel($rule),
            'priority' => (int) $rule->priority,
            'target_type' => $rule->target_type,
            'customer_scope' => $rule->customer_scope,
            'eligible_loyalty_tiers' => $rule->eligible_loyalty_tiers,
            'price_context' => $includePriceContext,
        ];
    }

    private function standardDiscountLabel(PricingRule $rule): string
    {
        return match ($rule->discount_type) {
            PricingRule::TYPE_PERCENTAGE => rtrim(rtrim(number_format((float) $rule->discount_value, 2, '.', ''), '0'), '.').'% OFF',
            PricingRule::TYPE_FIXED_AMOUNT => 'Hemat Rp '.number_format((float) $rule->discount_value, 0, ',', '.'),
            PricingRule::TYPE_FIXED_PRICE => 'Harga Rp '.number_format((float) $rule->discount_value, 0, ',', '.'),
            default => $rule->name,
        };
    }

    private function ruleTouchesProduct(PricingRule $rule, Product $product): bool
    {
        if ($this->matchesTarget($rule, $product)) {
            return true;
        }

        if ($rule->kind === PricingRule::KIND_BUNDLE_PRICE) {
            return $rule->bundleItems->contains(fn ($item) => (int) $item->product_id === (int) $product->id);
        }

        if ($rule->kind === PricingRule::KIND_BUY_X_GET_Y) {
            return $rule->buyGetItems->contains(fn ($item) => (int) $item->product_id === (int) $product->id);
        }

        return false;
    }
}
