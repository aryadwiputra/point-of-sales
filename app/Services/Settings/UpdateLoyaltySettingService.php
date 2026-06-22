<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Services\AuditLogService;
use App\Services\LoyaltyService;

class UpdateLoyaltySettingService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly LoyaltyService $loyaltyService
    ) {}

    public function execute(array $data): void
    {
        $before = $this->loyaltyService->settingsPayload();

        $this->loyaltyService->updateSettings($data);
        $this->loyaltyService->syncAllMemberTiers();

        $this->auditLogService->log(
            event: 'loyalty.setting.updated',
            module: 'loyalty_settings',
            auditable: ['target_label' => 'Loyalty Settings'],
            description: 'Pengaturan loyalty diperbarui.',
            before: $before,
            after: $this->loyaltyService->settingsPayload()
        );
    }
}
