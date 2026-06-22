<?php

declare(strict_types=1);

namespace App\Services\PricingRules;

use App\Models\PricingRule;
use App\Services\AuditLogService;

class DeletePricingRuleService
{
    public function __construct(
        private readonly PricingRulePayloadService $payloadService,
        private readonly AuditLogService $auditLogService
    ) {}

    public function execute(PricingRule $pricingRule): void
    {
        $before = $this->payloadService->auditPayload($pricingRule->load(['qtyBreaks', 'bundleItems', 'buyGetItems']));

        $pricingRule->delete();

        $this->auditLogService->log(
            event: 'pricing_rule.deleted',
            module: 'pricing_rules',
            auditable: $pricingRule,
            description: 'Rule promo/harga dihapus.',
            before: $before
        );
    }
}
