<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PricingRule;
use App\Models\PricingRuleBundleItem;
use App\Models\PricingRuleBuyGetItem;
use App\Models\PricingRuleQtyBreak;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Services\LoyaltyService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PricingRuleController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly LoyaltyService $loyaltyService,
        private readonly PricingService $pricingService
    ) {}

    public function index(Request $request)
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'target_type' => $request->input('target_type'),
            'kind' => $request->input('kind'),
        ];

        $rules = PricingRule::query()
            ->with(['product:id,title', 'category:id,name', 'creator:id,name', 'qtyBreaks', 'bundleItems', 'buyGetItems'])
            ->when($filters['search'], function ($query, $search) {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->when($filters['status'] !== null && $filters['status'] !== '', function ($query) use ($filters) {
                match ($filters['status']) {
                    'active' => $query->where('is_active', true)
                        ->where(function ($builder) {
                            $builder->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                        })
                        ->where(function ($builder) {
                            $builder->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                        }),
                    'scheduled' => $query->where('is_active', true)->whereNotNull('starts_at')->where('starts_at', '>', now()),
                    'expired' => $query->whereNotNull('ends_at')->where('ends_at', '<', now()),
                    'inactive' => $query->where('is_active', false),
                    default => null,
                };
            })
            ->when($filters['target_type'], function ($query, $targetType) {
                $query->where('target_type', $targetType);
            })
            ->when($filters['kind'], function ($query, $kind) {
                $query->where('kind', $kind);
            })
            ->orderByDesc('is_active')
            ->orderByDesc('priority')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (PricingRule $rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'kind' => $rule->kind,
                'is_active' => (bool) $rule->is_active,
                'priority' => (int) $rule->priority,
                'target_type' => $rule->target_type,
                'customer_scope' => $rule->customer_scope,
                'product' => $rule->product,
                'category' => $rule->category,
                'discount_type' => $rule->discount_type,
                'discount_value' => (float) $rule->discount_value,
                'starts_at' => optional($rule->starts_at)?->toIso8601String(),
                'ends_at' => optional($rule->ends_at)?->toIso8601String(),
                'status_label' => $rule->currentStatusLabel(),
                'qty_breaks_count' => $rule->qtyBreaks->count(),
                'bundle_items_count' => $rule->bundleItems->count(),
                'buy_get_items_count' => $rule->buyGetItems->count(),
            ]);

        $summaryBase = PricingRule::query()->get();

        return Inertia::render('Dashboard/PricingRules/Index', [
            'rules' => $rules,
            'filters' => $filters,
            'summary' => [
                'active' => $summaryBase->filter(fn (PricingRule $rule) => $rule->currentStatusLabel() === 'active')->count(),
                'scheduled' => $summaryBase->filter(fn (PricingRule $rule) => $rule->currentStatusLabel() === 'scheduled')->count(),
                'expired' => $summaryBase->filter(fn (PricingRule $rule) => $rule->currentStatusLabel() === 'expired')->count(),
                'inactive' => $summaryBase->filter(fn (PricingRule $rule) => $rule->currentStatusLabel() === 'inactive')->count(),
            ],
            'recentAudits' => AuditLog::query()
                ->where('module', 'pricing_rules')
                ->latest('id')
                ->limit(5)
                ->get(['id', 'event', 'description', 'created_at']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Dashboard/PricingRules/Create', $this->formPayload());
    }

    public function store(Request $request)
    {
        $validated = $this->validateRule($request);

        $rule = PricingRule::create([
            ...$validated['rule'],
            'created_by' => $request->user()?->id,
        ]);

        $this->syncRuleRelations($rule, $validated['relations']);

        $this->auditLogService->log(
            event: 'pricing_rule.created',
            module: 'pricing_rules',
            auditable: $rule,
            description: 'Rule promo/harga dibuat.',
            after: $this->auditPayload($rule->fresh(['qtyBreaks', 'bundleItems', 'buyGetItems']))
        );

        return redirect()
            ->route('pricing-rules.index')
            ->with('success', 'Rule promo berhasil dibuat.');
    }

    public function edit(PricingRule $pricingRule)
    {
        $pricingRule->load(['qtyBreaks', 'bundleItems', 'buyGetItems']);

        return Inertia::render('Dashboard/PricingRules/Edit', [
            ...$this->formPayload(),
            'rule' => [
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
            ],
        ]);
    }

    public function update(Request $request, PricingRule $pricingRule)
    {
        $before = $this->auditPayload($pricingRule->load(['qtyBreaks', 'bundleItems', 'buyGetItems']));
        $validated = $this->validateRule($request);

        $pricingRule->update($validated['rule']);
        $this->syncRuleRelations($pricingRule, $validated['relations']);

        $this->auditLogService->log(
            event: 'pricing_rule.updated',
            module: 'pricing_rules',
            auditable: $pricingRule,
            description: 'Rule promo/harga diperbarui.',
            before: $before,
            after: $this->auditPayload($pricingRule->fresh(['qtyBreaks', 'bundleItems', 'buyGetItems']))
        );

        return redirect()
            ->route('pricing-rules.index')
            ->with('success', 'Rule promo berhasil diperbarui.');
    }

    public function destroy(PricingRule $pricingRule)
    {
        $before = $this->auditPayload($pricingRule->load(['qtyBreaks', 'bundleItems', 'buyGetItems']));
        $pricingRule->delete();

        $this->auditLogService->log(
            event: 'pricing_rule.deleted',
            module: 'pricing_rules',
            auditable: $pricingRule,
            description: 'Rule promo/harga dihapus.',
            before: $before
        );

        return back()->with('success', 'Rule promo berhasil dihapus.');
    }

    public function preview(Request $request)
    {
        $validated = $this->validateRule($request);
        $rule = new PricingRule($validated['rule']);
        $rule->setRelation('qtyBreaks', collect($validated['relations']['qty_breaks'])->map(fn (array $break) => new PricingRuleQtyBreak($break)));
        $rule->setRelation('bundleItems', collect($validated['relations']['bundle_items'])->map(fn (array $item) => new PricingRuleBundleItem($item)));
        $rule->setRelation('buyGetItems', collect($validated['relations']['buy_get_items'])->map(fn (array $item) => new PricingRuleBuyGetItem($item)));

        $sampleCarts = $this->buildPreviewCarts($rule);
        $customer = $request->filled('preview_customer_id')
            ? Customer::find($request->integer('preview_customer_id'))
            : null;

        return response()->json([
            'success' => true,
            'data' => $this->pricingService->previewCartWithRules($sampleCarts, $customer, collect([$rule])),
        ]);
    }

    private function formPayload(): array
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

    private function validateRule(Request $request): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in([
                PricingRule::KIND_STANDARD_DISCOUNT,
                PricingRule::KIND_QTY_BREAK,
                PricingRule::KIND_BUNDLE_PRICE,
                PricingRule::KIND_BUY_X_GET_Y,
            ])],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['required', 'integer', 'min:0'],
            'target_type' => ['required', Rule::in([
                PricingRule::TARGET_ALL,
                PricingRule::TARGET_PRODUCT,
                PricingRule::TARGET_CATEGORY,
            ])],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'customer_scope' => ['required', Rule::in([
                PricingRule::SCOPE_ALL,
                PricingRule::SCOPE_WALK_IN,
                PricingRule::SCOPE_REGISTERED,
                PricingRule::SCOPE_MEMBER,
            ])],
            'eligible_loyalty_tiers' => ['nullable', 'array'],
            'eligible_loyalty_tiers.*' => ['string', Rule::in(array_keys($this->loyaltyService->tiers()))],
            'preview_quantity_multiplier' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        $kind = $request->input('kind');

        if (in_array($kind, [PricingRule::KIND_STANDARD_DISCOUNT, PricingRule::KIND_QTY_BREAK], true)) {
            $rules['discount_type'] = ['required', Rule::in([
                PricingRule::TYPE_PERCENTAGE,
                PricingRule::TYPE_FIXED_AMOUNT,
                PricingRule::TYPE_FIXED_PRICE,
            ])];
            $rules['discount_value'] = ['required', 'numeric', 'min:0.01'];
        }

        if ($kind === PricingRule::KIND_BUNDLE_PRICE) {
            $rules['discount_type'] = ['nullable', Rule::in([
                PricingRule::TYPE_PERCENTAGE,
                PricingRule::TYPE_FIXED_AMOUNT,
                PricingRule::TYPE_FIXED_PRICE,
            ])];
            $rules['discount_value'] = ['required', 'numeric', 'min:0.01'];
            $rules['bundle_items'] = ['required', 'array', 'min:2'];
            $rules['bundle_items.*.product_id'] = ['required', 'integer', 'exists:products,id'];
            $rules['bundle_items.*.quantity'] = ['required', 'integer', 'min:1'];
            $rules['bundle_items.*.sort_order'] = ['nullable', 'integer', 'min:0'];
        }

        if ($kind === PricingRule::KIND_BUY_X_GET_Y) {
            $rules['discount_type'] = ['nullable', Rule::in([
                PricingRule::TYPE_PERCENTAGE,
                PricingRule::TYPE_FIXED_AMOUNT,
                PricingRule::TYPE_FIXED_PRICE,
            ])];
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
            $rules['qty_breaks.*.discount_type'] = ['required', Rule::in([
                PricingRule::TYPE_PERCENTAGE,
                PricingRule::TYPE_FIXED_AMOUNT,
                PricingRule::TYPE_FIXED_PRICE,
            ])];
            $rules['qty_breaks.*.discount_value'] = ['required', 'numeric', 'min:0.01'];
            $rules['qty_breaks.*.sort_order'] = ['nullable', 'integer', 'min:0'];
        }

        $validated = $request->validate($rules);

        if ($validated['target_type'] === PricingRule::TARGET_PRODUCT && empty($validated['product_id'])) {
            $request->validate(['product_id' => ['required']]);
        }

        if ($validated['target_type'] === PricingRule::TARGET_CATEGORY && empty($validated['category_id'])) {
            $request->validate(['category_id' => ['required']]);
        }

        if ($validated['kind'] === PricingRule::KIND_BUY_X_GET_Y) {
            $buyCount = collect($validated['buy_get_items'] ?? [])->where('role', PricingRuleBuyGetItem::ROLE_BUY)->count();
            $getCount = collect($validated['buy_get_items'] ?? [])->where('role', PricingRuleBuyGetItem::ROLE_GET)->count();

            if ($buyCount === 0 || $getCount === 0) {
                $request->validate(['buy_get_items' => ['required', 'array', 'min:2']]);
            }
        }

        if ($validated['target_type'] !== PricingRule::TARGET_PRODUCT) {
            $validated['product_id'] = null;
        }

        if ($validated['target_type'] !== PricingRule::TARGET_CATEGORY) {
            $validated['category_id'] = null;
        }

        if (
            in_array($validated['kind'], [PricingRule::KIND_STANDARD_DISCOUNT, PricingRule::KIND_QTY_BREAK], true)
            &&
            ($validated['discount_type'] ?? null) === PricingRule::TYPE_PERCENTAGE
            && (float) ($validated['discount_value'] ?? 0) > 100
        ) {
            $request->validate(['discount_value' => ['max:100']]);
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

    private function syncRuleRelations(PricingRule $rule, array $relations): void
    {
        $rule->qtyBreaks()->delete();
        foreach ($relations['qty_breaks'] as $payload) {
            $rule->qtyBreaks()->create($payload);
        }

        $rule->bundleItems()->delete();
        foreach ($relations['bundle_items'] as $payload) {
            $rule->bundleItems()->create($payload);
        }

        $rule->buyGetItems()->delete();
        foreach ($relations['buy_get_items'] as $payload) {
            $rule->buyGetItems()->create($payload);
        }
    }

    private function auditPayload(PricingRule $rule): array
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
