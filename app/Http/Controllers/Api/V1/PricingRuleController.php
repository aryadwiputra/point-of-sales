<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\PricingRuleController as AppsPricingRuleController;
use App\Http\Traits\ApiResponder;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\PricingRule;
use App\Models\PricingRuleBundleItem;
use App\Models\PricingRuleBuyGetItem;
use App\Models\PricingRuleQtyBreak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingRuleController extends AppsPricingRuleController
{
    use ApiResponder;

    /**
     * GET /api/v1/pricing-rules
     */
    public function listRules(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'target_type' => $request->input('target_type'),
            'kind' => $request->input('kind'),
        ];

        $outlet = $this->outletAccess->activeOutlet($request);
        $rules = PricingRule::query()
            ->where(function ($query) use ($outlet) {
                $query->whereNull('outlet_id');
                if ($outlet) {
                    $query->orWhere('outlet_id', $outlet->id);
                }
            })
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
            ->paginate($this->perPage())
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

        $summaryBase = PricingRule::query()
            ->where(function ($query) use ($outlet) {
                $query->whereNull('outlet_id');
                if ($outlet) {
                    $query->orWhere('outlet_id', $outlet->id);
                }
            })->get();

        return $this->paginated($rules, [
            'filters' => $filters,
            'summary' => [
                'active' => $summaryBase->filter(fn (PricingRule $rule) => $rule->currentStatusLabel() === 'active')->count(),
                'scheduled' => $summaryBase->filter(fn (PricingRule $rule) => $rule->currentStatusLabel() === 'scheduled')->count(),
                'expired' => $summaryBase->filter(fn (PricingRule $rule) => $rule->currentStatusLabel() === 'expired')->count(),
                'inactive' => $summaryBase->filter(fn (PricingRule $rule) => $rule->currentStatusLabel() === 'inactive')->count(),
            ],
            'recent_audits' => AuditLog::query()
                ->where('module', 'pricing_rules')
                ->latest('id')
                ->limit(5)
                ->get(['id', 'event', 'description', 'created_at']),
        ]);
    }

    /**
     * GET /api/v1/pricing-rules/create
     */
    public function createData(): JsonResponse
    {
        return $this->ok($this->formPayload());
    }

    /**
     * POST /api/v1/pricing-rules
     */
    public function storeRule(Request $request): JsonResponse
    {
        $validated = $this->validateRule($request);
        $validated['rule']['outlet_id'] = $this->outletAccess->activeOutlet($request)?->id;

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

        return $this->created($this->rulePayload($rule));
    }

    /**
     * GET /api/v1/pricing-rules/{pricingRule}
     */
    public function showRule(PricingRule $pricingRule): JsonResponse
    {
        abort_unless($this->canUseRule($pricingRule), 404);

        return $this->ok([
            'rule' => $this->rulePayload($pricingRule),
            ...$this->formPayload(),
        ]);
    }

    /**
     * PUT /api/v1/pricing-rules/{pricingRule}
     */
    public function updateRule(Request $request, PricingRule $pricingRule): JsonResponse
    {
        abort_unless($this->canUseRule($pricingRule), 404);
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

        return $this->ok(['rule' => $this->rulePayload($pricingRule->fresh())]);
    }

    /**
     * DELETE /api/v1/pricing-rules/{pricingRule}
     */
    public function destroyRule(PricingRule $pricingRule): JsonResponse
    {
        abort_unless($this->canUseRule($pricingRule), 404);
        $before = $this->auditPayload($pricingRule->load(['qtyBreaks', 'bundleItems', 'buyGetItems']));
        $pricingRule->delete();

        $this->auditLogService->log(
            event: 'pricing_rule.deleted',
            module: 'pricing_rules',
            auditable: $pricingRule,
            description: 'Rule promo/harga dihapus.',
            before: $before
        );

        return $this->noContent();
    }

    /**
     * POST /api/v1/pricing-rules/preview
     */
    public function previewRule(Request $request): JsonResponse
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

        return $this->ok($this->pricingService->previewCartWithRules(
            $sampleCarts,
            $customer,
            collect([$rule]),
            $this->outletAccess->activeOutlet($request)
        ));
    }

    /**
     * Serialize a rule with its relations.
     */
    protected function rulePayload(PricingRule $rule): array
    {
        $rule->load(['qtyBreaks', 'bundleItems', 'buyGetItems']);

        return [
            ...$rule->toArray(),
            'qty_breaks' => $rule->qtyBreaks->map(fn (PricingRuleQtyBreak $break) => [
                'id' => $break->id,
                'min_qty' => (int) $break->min_qty,
                'discount_type' => $break->discount_type,
                'discount_value' => (float) $break->discount_value,
                'sort_order' => (int) $break->sort_order,
            ])->values()->all(),
            'bundle_items' => $rule->bundleItems->map(fn (PricingRuleBundleItem $item) => [
                'id' => $item->id,
                'product_id' => (int) $item->product_id,
                'quantity' => (int) $item->quantity,
                'sort_order' => (int) $item->sort_order,
            ])->values()->all(),
            'buy_get_items' => $rule->buyGetItems->map(fn (PricingRuleBuyGetItem $item) => [
                'id' => $item->id,
                'product_id' => (int) $item->product_id,
                'role' => $item->role,
                'quantity' => (int) $item->quantity,
                'sort_order' => (int) $item->sort_order,
            ])->values()->all(),
        ];
    }
}
