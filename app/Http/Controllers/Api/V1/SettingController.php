<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\SettingController as AppsSettingController;
use App\Http\Traits\ApiResponder;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SettingController extends AppsSettingController
{
    use ApiResponder;

    /**
     * GET /api/v1/settings/target
     */
    public function showTarget(): JsonResponse
    {
        $outlet = $this->outletAccessService->activeOutlet(request());

        return $this->ok([
            'settings' => [
                'monthly_sales_target' => Setting::getForOutlet('monthly_sales_target', $outlet, 0),
            ],
        ]);
    }

    /**
     * POST /api/v1/settings/target
     */
    public function updateTarget(Request $request): JsonResponse
    {
        $request->validate([
            'monthly_sales_target' => 'required|numeric|min:0',
        ]);

        Setting::setForOutlet(
            'monthly_sales_target',
            $request->monthly_sales_target,
            $this->outletAccessService->activeOutlet($request),
            'Target penjualan bulanan'
        );

        return $this->ok(['settings' => [
            'monthly_sales_target' => $request->monthly_sales_target,
        ]]);
    }

    /**
     * GET /api/v1/settings/store
     */
    public function storeProfile(): JsonResponse
    {
        $outlet = $this->outletAccessService->activeOutlet(request());

        return $this->ok([
            'settings' => [
                'store_name' => Setting::getForOutlet('store_name', $outlet, ''),
                'store_logo' => Setting::getForOutlet('store_logo', $outlet, ''),
                'store_address' => Setting::getForOutlet('store_address', $outlet, ''),
                'store_phone' => Setting::getForOutlet('store_phone', $outlet, ''),
                'store_email' => Setting::getForOutlet('store_email', $outlet, ''),
                'store_website' => Setting::getForOutlet('store_website', $outlet, ''),
                'store_city' => Setting::getForOutlet('store_city', $outlet, ''),
                'store_npwp' => Setting::get('store_npwp', ''),
                'store_nib' => Setting::get('store_nib', ''),
                'tax_default_rate' => Setting::get('tax_default_rate', '11.00'),
            ],
        ]);
    }

    /**
     * POST /api/v1/settings/store
     */
    public function updateStoreProfile(Request $request): JsonResponse
    {
        $outlet = $this->outletAccessService->activeOutlet($request);
        $request->validate([
            'store_name' => 'required|string|max:255',
            'store_address' => 'required|string|max:500',
            'store_phone' => 'nullable|string|max:50',
            'store_email' => 'nullable|email|max:255',
            'store_website' => 'nullable|string|max:255',
            'store_city' => 'nullable|string|max:255',
            'store_logo' => 'nullable|image|max:2048',
            'store_npwp' => 'nullable|string|max:20',
            'store_nib' => 'nullable|string|max:30',
            'tax_default_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $before = [
            'store_name' => Setting::getForOutlet('store_name', $outlet, ''),
            'store_address' => Setting::getForOutlet('store_address', $outlet, ''),
            'store_phone' => Setting::getForOutlet('store_phone', $outlet, ''),
            'store_email' => Setting::getForOutlet('store_email', $outlet, ''),
            'store_website' => Setting::getForOutlet('store_website', $outlet, ''),
            'store_city' => Setting::getForOutlet('store_city', $outlet, ''),
            'store_logo_changed' => false,
        ];

        $logoPath = Setting::getForOutlet('store_logo', $outlet);
        $logoChanged = false;

        if ($request->file('store_logo')) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }
            $logoPath = $request->file('store_logo')->store('store', 'public');
            $logoChanged = true;
        }

        Setting::setForOutlet('store_name', $request->store_name, $outlet, 'Nama toko');
        Setting::setForOutlet('store_address', $request->store_address, $outlet, 'Alamat toko');
        Setting::setForOutlet('store_phone', $request->store_phone, $outlet, 'Telepon toko');
        Setting::setForOutlet('store_email', $request->store_email, $outlet, 'Email toko');
        Setting::setForOutlet('store_website', $request->store_website, $outlet, 'Website toko');
        Setting::setForOutlet('store_city', $request->store_city, $outlet, 'Kota/Kabupaten toko');
        Setting::setForOutlet('store_logo', $logoPath, $outlet, 'Logo toko');
        Setting::set('store_npwp', $request->store_npwp, 'NPWP Toko');
        Setting::set('store_nib', $request->store_nib, 'NIB Toko');
        Setting::set('tax_default_rate', $request->tax_default_rate, 'Default tarif PPN (%)');

        $this->auditLogService->log(
            event: 'store.setting.updated',
            module: 'store_settings',
            auditable: ['target_label' => 'Store Profile'],
            description: 'Profil toko diperbarui.',
            before: $before,
            after: [
                'store_name' => $request->store_name,
                'store_address' => $request->store_address,
                'store_phone' => $request->store_phone,
                'store_email' => $request->store_email,
                'store_website' => $request->store_website,
                'store_city' => $request->store_city,
                'store_logo_changed' => $logoChanged,
                'store_npwp' => $request->store_npwp ? '***' : null,
                'tax_default_rate' => $request->tax_default_rate,
            ],
        );

        return $this->ok(['message' => 'Profil toko berhasil diperbarui']);
    }

    /**
     * GET /api/v1/settings/printer
     */
    public function showPrinter(): JsonResponse
    {
        $outlet = $this->outletAccessService->activeOutlet(request());

        return $this->ok([
            'settings' => [
                'printer_auto_print' => Setting::getBoolForOutlet('printer_auto_print', $outlet, false),
                'printer_paper_size' => Setting::getForOutlet('printer_paper_size', $outlet, '80mm'),
            ],
        ]);
    }

    /**
     * POST /api/v1/settings/printer
     */
    public function updatePrinter(Request $request): JsonResponse
    {
        $outlet = $this->outletAccessService->activeOutlet($request);
        $validated = $request->validate([
            'printer_auto_print' => ['boolean'],
            'printer_paper_size' => ['required', 'in:80mm,58mm'],
        ]);

        Setting::setForOutlet('printer_auto_print', $validated['printer_auto_print'] ? '1' : '0', $outlet, 'Auto-print receipt setelah transaksi');
        Setting::setForOutlet('printer_paper_size', $validated['printer_paper_size'], $outlet, 'Ukuran kertas printer thermal');

        return $this->ok(['settings' => [
            'printer_auto_print' => (bool) $validated['printer_auto_print'],
            'printer_paper_size' => $validated['printer_paper_size'],
        ]]);
    }

    /**
     * GET /api/v1/settings/loyalty
     */
    public function showLoyalty(): JsonResponse
    {
        return $this->ok([
            'settings' => $this->loyaltyService->settingsPayload(),
        ]);
    }

    /**
     * POST /api/v1/settings/loyalty
     */
    public function updateLoyalty(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enable_earn' => ['required', 'boolean'],
            'enable_redeem' => ['required', 'boolean'],
            'earn_rate_amount' => ['required', 'integer', 'min:1'],
            'redeem_point_value' => ['required', 'integer', 'min:1'],
            'tiers' => ['required', 'array'],
            'tiers.regular' => ['required', 'integer', 'min:0'],
            'tiers.silver' => ['required', 'integer', 'min:0'],
            'tiers.gold' => ['required', 'integer', 'min:0'],
            'tiers.platinum' => ['required', 'integer', 'min:0'],
        ]);

        $orderedThresholds = [
            'regular' => (int) $validated['tiers']['regular'],
            'silver' => (int) $validated['tiers']['silver'],
            'gold' => (int) $validated['tiers']['gold'],
            'platinum' => (int) $validated['tiers']['platinum'],
        ];

        if (
            $orderedThresholds['silver'] < $orderedThresholds['regular']
            || $orderedThresholds['gold'] < $orderedThresholds['silver']
            || $orderedThresholds['platinum'] < $orderedThresholds['gold']
        ) {
            throw ValidationException::withMessages([
                'tiers' => 'Threshold tier harus berurutan dari Regular ke Platinum.',
            ]);
        }

        $before = $this->loyaltyService->settingsPayload();
        $this->loyaltyService->updateSettings([
            ...$validated,
            'tiers' => $orderedThresholds,
        ]);
        $this->loyaltyService->syncAllMemberTiers();

        $this->auditLogService->log(
            event: 'loyalty.setting.updated',
            module: 'loyalty_settings',
            auditable: ['target_label' => 'Loyalty Settings'],
            description: 'Pengaturan loyalty diperbarui.',
            before: $before,
            after: $this->loyaltyService->settingsPayload()
        );

        return $this->ok(['settings' => $this->loyaltyService->settingsPayload()]);
    }

    /**
     * GET /api/v1/settings/whatsapp
     */
    public function showWhatsapp(): JsonResponse
    {
        $outlet = $this->outletAccessService->activeOutlet(request());
        $waStatus = ['connected' => false, 'phone' => null, 'qr' => null];
        if (Setting::getForOutlet('wa_service_url', $outlet)) {
            try {
                $waStatus = $this->whatsAppService->status($outlet);
            } catch (\Exception $e) {
                $waStatus['error'] = $e->getMessage();
            }
        }

        return $this->ok([
            'settings' => [
                'wa_service_url' => Setting::getForOutlet('wa_service_url', $outlet, ''),
                'wa_enabled' => Setting::getBoolForOutlet('wa_enabled', $outlet, false),
                'wa_auto_reminder' => Setting::getBoolForOutlet('wa_auto_reminder', $outlet, false),
                'wa_auto_invoice' => Setting::getBoolForOutlet('wa_auto_invoice', $outlet, false),
            ],
            'wa_status' => $waStatus,
        ]);
    }

    /**
     * POST /api/v1/settings/whatsapp
     */
    public function updateWhatsapp(Request $request): JsonResponse
    {
        $outlet = $this->outletAccessService->activeOutlet($request);
        $validated = $request->validate([
            'wa_service_url' => ['nullable', 'string', 'max:255'],
            'wa_enabled' => ['boolean'],
            'wa_auto_reminder' => ['boolean'],
            'wa_auto_invoice' => ['boolean'],
        ]);

        Setting::setForOutlet('wa_service_url', $validated['wa_service_url'] ?? '', $outlet, 'URL service WhatsApp');
        Setting::setForOutlet('wa_enabled', ($validated['wa_enabled'] ?? false) ? '1' : '0', $outlet, 'WhatsApp gateway aktif');
        Setting::setForOutlet('wa_auto_reminder', ($validated['wa_auto_reminder'] ?? false) ? '1' : '0', $outlet, 'Auto-kirim reminder via WA');
        Setting::setForOutlet('wa_auto_invoice', ($validated['wa_auto_invoice'] ?? false) ? '1' : '0', $outlet, 'Auto-kirim invoice via WA');

        return $this->ok(['message' => 'Pengaturan WhatsApp disimpan.']);
    }

    /**
     * POST /api/v1/settings/whatsapp/test
     */
    public function testWhatsapp(Request $request): JsonResponse
    {
        $request->validate(['target' => 'required|string']);
        $outlet = $this->outletAccessService->activeOutlet($request);

        $sent = $this->whatsAppService->send(
            $request->target,
            'Test pesan dari Point of Sales — '.config('app.url'),
            $outlet
        );

        return $this->ok(['status' => $sent]);
    }

    /**
     * POST /api/v1/settings/whatsapp/start
     */
    public function startWhatsapp(Request $request): JsonResponse
    {
        $result = $this->whatsAppService->start($this->outletAccessService->activeOutlet($request));

        return $this->ok($result);
    }

    /**
     * GET /api/v1/settings/whatsapp/status
     */
    public function whatsappStatus(Request $request): JsonResponse
    {
        $status = $this->whatsAppService->status($this->outletAccessService->activeOutlet($request));

        return $this->ok($status);
    }

    /**
     * POST /api/v1/settings/whatsapp/disconnect
     */
    public function disconnectWhatsapp(Request $request): JsonResponse
    {
        $this->whatsAppService->disconnect($this->outletAccessService->activeOutlet($request));

        return $this->ok(['status' => true]);
    }
}
