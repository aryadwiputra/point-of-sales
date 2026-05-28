<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Services\LoyaltyService;

class LoyaltySettingQueryService
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService
    ) {}

    public function execute(): array
    {
        return [
            'settings' => $this->loyaltyService->settingsPayload(),
        ];
    }
}
