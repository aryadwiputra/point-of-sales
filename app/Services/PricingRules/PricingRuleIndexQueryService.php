<?php

declare(strict_types=1);

namespace App\Services\PricingRules;

use App\Models\AuditLog;
use App\Models\PricingRule;

class PricingRuleIndexQueryService
{
    public function execute(array $filters): array
    {
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

        return [
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
        ];
    }
}
