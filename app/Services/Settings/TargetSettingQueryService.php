<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;

class TargetSettingQueryService
{
    public function execute(): array
    {
        return [
            'settings' => [
                'monthly_sales_target' => Setting::get('monthly_sales_target', 0),
            ],
        ];
    }
}
