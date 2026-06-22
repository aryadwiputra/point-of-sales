<?php

declare(strict_types=1);

namespace App\Http\Requests\PricingRule;

use App\Models\PricingRule;
use App\Models\PricingRuleBuyGetItem;
use App\Services\LoyaltyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePricingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $kind = $this->input('kind');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in($this->kindValues())],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['required', 'integer', 'min:0'],
            'target_type' => ['required', Rule::in($this->targetValues())],
            'product_id' => ['nullable', 'integer', 'exists:products,id', 'required_if:target_type,'.PricingRule::TARGET_PRODUCT],
            'category_id' => ['nullable', 'integer', 'exists:categories,id', 'required_if:target_type,'.PricingRule::TARGET_CATEGORY],
            'customer_scope' => ['required', Rule::in($this->scopeValues())],
            'eligible_loyalty_tiers' => ['nullable', 'array'],
            'eligible_loyalty_tiers.*' => ['string', Rule::in(array_keys(app(LoyaltyService::class)->tiers()))],
            'preview_quantity_multiplier' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if (in_array($kind, [PricingRule::KIND_STANDARD_DISCOUNT, PricingRule::KIND_QTY_BREAK], true)) {
            $rules['discount_type'] = ['required', Rule::in($this->discountTypeValues())];
            $rules['discount_value'] = ['required', 'numeric', 'min:0.01'];

            if ($this->input('discount_type') === PricingRule::TYPE_PERCENTAGE) {
                $rules['discount_value'][] = 'max:100';
            }
        }

        if ($kind === PricingRule::KIND_BUNDLE_PRICE) {
            $rules['discount_type'] = ['nullable', Rule::in($this->discountTypeValues())];
            $rules['discount_value'] = ['required', 'numeric', 'min:0.01'];
            $rules['bundle_items'] = ['required', 'array', 'min:2'];
            $rules['bundle_items.*.product_id'] = ['required', 'integer', 'exists:products,id'];
            $rules['bundle_items.*.quantity'] = ['required', 'integer', 'min:1'];
            $rules['bundle_items.*.sort_order'] = ['nullable', 'integer', 'min:0'];
        }

        if ($kind === PricingRule::KIND_BUY_X_GET_Y) {
            $rules['discount_type'] = ['nullable', Rule::in($this->discountTypeValues())];
            $rules['discount_value'] = ['nullable', 'numeric', 'min:0'];
            $rules['buy_get_items'] = ['required', 'array', 'min:2'];
            $rules['buy_get_items.*.product_id'] = ['required', 'integer', 'exists:products,id'];
            $rules['buy_get_items.*.role'] = ['required', Rule::in([
                PricingRuleBuyGetItem::ROLE_BUY,
                PricingRuleBuyGetItem::ROLE_GET,
            ])];
            $rules['buy_get_items.*.quantity'] = ['required', 'integer', 'min:1'];
            $rules['buy_get_items.*.sort_order'] = ['nullable', 'integer', 'min:0'];
        }

        if ($kind === PricingRule::KIND_QTY_BREAK) {
            $rules['qty_breaks'] = ['required', 'array', 'min:1'];
            $rules['qty_breaks.*.min_qty'] = ['required', 'integer', 'min:1'];
            $rules['qty_breaks.*.discount_type'] = ['required', Rule::in($this->discountTypeValues())];
            $rules['qty_breaks.*.discount_value'] = ['required', 'numeric', 'min:0.01'];
            $rules['qty_breaks.*.sort_order'] = ['nullable', 'integer', 'min:0'];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('kind') !== PricingRule::KIND_BUY_X_GET_Y || $validator->errors()->isNotEmpty()) {
                return;
            }

            $items = collect($this->input('buy_get_items', []));

            if (
                $items->where('role', PricingRuleBuyGetItem::ROLE_BUY)->isEmpty()
                || $items->where('role', PricingRuleBuyGetItem::ROLE_GET)->isEmpty()
            ) {
                $validator->errors()->add('buy_get_items', 'Buy X Get Y wajib memiliki item buy dan get.');
            }
        });
    }

    public function normalizedPayload(): array
    {
        $validated = $this->validated();

        if ($validated['target_type'] !== PricingRule::TARGET_PRODUCT) {
            $validated['product_id'] = null;
        }

        if ($validated['target_type'] !== PricingRule::TARGET_CATEGORY) {
            $validated['category_id'] = null;
        }

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['eligible_loyalty_tiers'] = $validated['customer_scope'] === PricingRule::SCOPE_MEMBER
            ? array_values(array_unique($validated['eligible_loyalty_tiers'] ?? []))
            : null;
        $validated['preview_quantity_multiplier'] = max(1, (int) ($validated['preview_quantity_multiplier'] ?? 1));

        if ($validated['kind'] === PricingRule::KIND_QTY_BREAK) {
            $validated['qty_breaks'] = collect($validated['qty_breaks'] ?? [])
                ->map(fn (array $break, int $index) => [
                    'min_qty' => (int) $break['min_qty'],
                    'discount_type' => $break['discount_type'],
                    'discount_value' => (float) $break['discount_value'],
                    'sort_order' => (int) ($break['sort_order'] ?? $index),
                ])
                ->sortBy('min_qty')
                ->values()
                ->all();
        }

        $validated['bundle_items'] = collect($validated['bundle_items'] ?? [])
            ->map(fn (array $item, int $index) => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'sort_order' => (int) ($item['sort_order'] ?? $index),
            ])
            ->values()
            ->all();

        $validated['buy_get_items'] = collect($validated['buy_get_items'] ?? [])
            ->map(fn (array $item, int $index) => [
                'product_id' => (int) $item['product_id'],
                'role' => $item['role'],
                'quantity' => (int) $item['quantity'],
                'sort_order' => (int) ($item['sort_order'] ?? $index),
            ])
            ->values()
            ->all();

        if ($validated['kind'] === PricingRule::KIND_BUNDLE_PRICE) {
            $validated['discount_type'] = PricingRule::TYPE_FIXED_PRICE;
        }

        if ($validated['kind'] === PricingRule::KIND_BUY_X_GET_Y) {
            $validated['discount_type'] = PricingRule::TYPE_FIXED_AMOUNT;
            $validated['discount_value'] = 0;
        }

        return [
            'rule' => collect($validated)
                ->only([
                    'name',
                    'kind',
                    'is_active',
                    'priority',
                    'target_type',
                    'product_id',
                    'category_id',
                    'customer_scope',
                    'eligible_loyalty_tiers',
                    'discount_type',
                    'discount_value',
                    'preview_quantity_multiplier',
                    'starts_at',
                    'ends_at',
                    'notes',
                ])
                ->all(),
            'relations' => [
                'qty_breaks' => $validated['kind'] === PricingRule::KIND_QTY_BREAK ? ($validated['qty_breaks'] ?? []) : [],
                'bundle_items' => $validated['kind'] === PricingRule::KIND_BUNDLE_PRICE ? $validated['bundle_items'] : [],
                'buy_get_items' => $validated['kind'] === PricingRule::KIND_BUY_X_GET_Y ? $validated['buy_get_items'] : [],
            ],
        ];
    }

    private function kindValues(): array
    {
        return [
            PricingRule::KIND_STANDARD_DISCOUNT,
            PricingRule::KIND_QTY_BREAK,
            PricingRule::KIND_BUNDLE_PRICE,
            PricingRule::KIND_BUY_X_GET_Y,
        ];
    }

    private function targetValues(): array
    {
        return [
            PricingRule::TARGET_ALL,
            PricingRule::TARGET_PRODUCT,
            PricingRule::TARGET_CATEGORY,
        ];
    }

    private function scopeValues(): array
    {
        return [
            PricingRule::SCOPE_ALL,
            PricingRule::SCOPE_WALK_IN,
            PricingRule::SCOPE_REGISTERED,
            PricingRule::SCOPE_MEMBER,
        ];
    }

    private function discountTypeValues(): array
    {
        return [
            PricingRule::TYPE_PERCENTAGE,
            PricingRule::TYPE_FIXED_AMOUNT,
            PricingRule::TYPE_FIXED_PRICE,
        ];
    }
}
