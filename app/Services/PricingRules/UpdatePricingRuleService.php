<?php

declare(strict_types=1);

namespace App\Services\PricingRules;

use App\Models\PricingRule;
use App\Services\AuditLogService;

class UpdatePricingRuleService
{
    public function __construct(
        private readonly PricingRuleRelationSyncService $relationSync,
        private readonly PricingRulePayloadService $payloadService,
        private readonly AuditLogService $auditLogService
    ) {}

    public function execute(PricingRule $pricingRule, array $payload): void
    {
        $before = $this->payloadService->auditPayload($pricingRule->load(['qtyBreaks', 'bundleItems', 'buyGetItems']));

        $pricingRule->update($payload['rule']);
        $this->relationSync->sync($pricingRule, $payload['relations']);

        $this->auditLogService->log(
            event: 'pricing_rule.updated',
            module: 'pricing_rules',
            auditable: $pricingRule,
            description: 'Rule promo/harga diperbarui.',
            before: $before,
            after: $this->payloadService->auditPayload($pricingRule->fresh(['qtyBreaks', 'bundleItems', 'buyGetItems']))
        );
    }
}
