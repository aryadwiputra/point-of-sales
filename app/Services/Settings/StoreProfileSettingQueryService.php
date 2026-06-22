<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;

class StoreProfileSettingQueryService
{
    public function execute(): array
    {
        return [
            'settings' => [
                'store_name' => Setting::get('store_name', ''),
                'store_logo' => Setting::get('store_logo', ''),
                'store_address' => Setting::get('store_address', ''),
                'store_phone' => Setting::get('store_phone', ''),
                'store_email' => Setting::get('store_email', ''),
                'store_website' => Setting::get('store_website', ''),
                'store_city' => Setting::get('store_city', ''),
                'product_display_mode' => Setting::productDisplayMode(),
            ],
        ];
    }
}
