<?php

declare(strict_types=1);

namespace App\Services\PricingRules;

use App\Models\PricingRule;

class PricingRuleRelationSyncService
{
    public function sync(PricingRule $rule, array $relations): void
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
}
