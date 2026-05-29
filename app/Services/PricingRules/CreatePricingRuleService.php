<?php

declare(strict_types=1);

namespace App\Services\PricingRules;

use App\Models\PricingRule;
use App\Services\AuditLogService;

class CreatePricingRuleService
{
    public function __construct(
        private readonly PricingRuleRelationSyncService $relationSync,
        private readonly PricingRulePayloadService $payloadService,
        private readonly AuditLogService $auditLogService
    ) {}

    public function execute(array $payload, ?int $userId): PricingRule
    {
        $rule = PricingRule::create([
            ...$payload['rule'],
            'created_by' => $userId,
        ]);

        $this->relationSync->sync($rule, $payload['relations']);

        $this->auditLogService->log(
            event: 'pricing_rule.created',
            module: 'pricing_rules',
            auditable: $rule,
            description: 'Rule promo/harga dibuat.',
            after: $this->payloadService->auditPayload($rule->fresh(['qtyBreaks', 'bundleItems', 'buyGetItems']))
        );

        return $rule;
    }
}
