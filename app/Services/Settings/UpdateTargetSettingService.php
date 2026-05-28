<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;

class UpdateTargetSettingService
{
    public function execute(array $data): void
    {
        Setting::set(
            'monthly_sales_target',
            $data['monthly_sales_target'],
            'Target penjualan bulanan'
        );
    }
}
