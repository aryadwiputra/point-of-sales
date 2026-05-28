<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;
use App\Services\AuditLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateStoreProfileSettingService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function execute(array $data): void
    {
        $before = [
            'store_name' => Setting::get('store_name', ''),
            'store_address' => Setting::get('store_address', ''),
            'store_phone' => Setting::get('store_phone', ''),
            'store_email' => Setting::get('store_email', ''),
            'store_website' => Setting::get('store_website', ''),
            'store_city' => Setting::get('store_city', ''),
            'store_logo_changed' => false,
            'product_display_mode' => Setting::productDisplayMode(),
        ];

        $logoPath = Setting::get('store_logo');
        $logoChanged = false;

        if (($data['store_logo'] ?? null) instanceof UploadedFile) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            $logoPath = $data['store_logo']->store('store', 'public');
            $logoChanged = true;
        }

        Setting::set('store_name', $data['store_name'], 'Nama toko');
        Setting::set('store_address', $data['store_address'], 'Alamat toko');
        Setting::set('store_phone', $data['store_phone'] ?? null, 'Telepon toko');
        Setting::set('store_email', $data['store_email'] ?? null, 'Email toko');
        Setting::set('store_website', $data['store_website'] ?? null, 'Website toko');
        Setting::set('store_city', $data['store_city'] ?? null, 'Kota/Kabupaten toko');
        Setting::set('store_logo', $logoPath, 'Logo toko');
        Setting::set('product_display_mode', $data['product_display_mode'], 'Mode tampilan produk dan kategori');

        $this->auditLogService->log(
            event: 'store.setting.updated',
            module: 'store_settings',
            auditable: ['target_label' => 'Store Profile'],
            description: 'Profil toko diperbarui.',
            before: $before,
            after: [
                'store_name' => $data['store_name'],
                'store_address' => $data['store_address'],
                'store_phone' => $data['store_phone'] ?? null,
                'store_email' => $data['store_email'] ?? null,
                'store_website' => $data['store_website'] ?? null,
                'store_city' => $data['store_city'] ?? null,
                'store_logo_changed' => $logoChanged,
                'product_display_mode' => $data['product_display_mode'],
            ],
        );
    }
}
