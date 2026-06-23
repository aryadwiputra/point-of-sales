<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogService;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly LoyaltyService $loyaltyService
    ) {}

    /**
     * Show the target settings page
     */
    public function target()
    {
        $settings = [
            'monthly_sales_target' => Setting::get('monthly_sales_target', 0),
        ];

        return Inertia::render('Dashboard/Settings/Target', [
            'settings' => $settings,
        ]);
    }

    /**
     * Update target settings
     */
    public function updateTarget(Request $request)
    {
        $request->validate([
            'monthly_sales_target' => 'required|numeric|min:0',
        ]);

        Setting::set(
            'monthly_sales_target',
            $request->monthly_sales_target,
            'Target penjualan bulanan'
        );

        return back()->with('success', 'Target berhasil disimpan');
    }

    /**
     * Store profile settings page
     */
    public function storeProfile()
    {
        $settings = [
            'store_name' => Setting::get('store_name', ''),
            'store_logo' => Setting::get('store_logo', ''),
            'store_address' => Setting::get('store_address', ''),
            'store_phone' => Setting::get('store_phone', ''),
            'store_email' => Setting::get('store_email', ''),
            'store_website' => Setting::get('store_website', ''),
            'store_city' => Setting::get('store_city', ''),
            'store_npwp' => Setting::get('store_npwp', ''),
            'store_nib' => Setting::get('store_nib', ''),
            'tax_default_rate' => Setting::get('tax_default_rate', '11.00'),
        ];

        return Inertia::render('Dashboard/Settings/Store', [
            'settings' => $settings,
        ]);
    }

    /**
     * Update store profile settings
     */
    public function updateStoreProfile(Request $request)
    {
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
            'store_name' => Setting::get('store_name', ''),
            'store_address' => Setting::get('store_address', ''),
            'store_phone' => Setting::get('store_phone', ''),
            'store_email' => Setting::get('store_email', ''),
            'store_website' => Setting::get('store_website', ''),
            'store_city' => Setting::get('store_city', ''),
            'store_logo_changed' => false,
        ];

        $logoPath = Setting::get('store_logo');
        $logoChanged = false;

        if ($request->file('store_logo')) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }
            $logoPath = $request->file('store_logo')->store('store', 'public');
            $logoChanged = true;
        }

        Setting::set('store_name', $request->store_name, 'Nama toko');
        Setting::set('store_address', $request->store_address, 'Alamat toko');
        Setting::set('store_phone', $request->store_phone, 'Telepon toko');
        Setting::set('store_email', $request->store_email, 'Email toko');
        Setting::set('store_website', $request->store_website, 'Website toko');
        Setting::set('store_city', $request->store_city, 'Kota/Kabupaten toko');
        Setting::set('store_logo', $logoPath, 'Logo toko');
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

        return back()->with('success', 'Profil toko berhasil diperbarui');
    }

    public function printer()
    {
        return Inertia::render('Dashboard/Settings/Printer', [
            'settings' => [
                'printer_auto_print' => Setting::getBool('printer_auto_print', false),
                'printer_paper_size' => Setting::get('printer_paper_size', '80mm'),
            ],
        ]);
    }

    public function updatePrinter(Request $request)
    {
        $validated = $request->validate([
            'printer_auto_print' => ['boolean'],
            'printer_paper_size' => ['required', 'in:80mm,58mm'],
        ]);

        Setting::set('printer_auto_print', $validated['printer_auto_print'] ? '1' : '0', 'Auto-print receipt setelah transaksi');
        Setting::set('printer_paper_size', $validated['printer_paper_size'], 'Ukuran kertas printer thermal');

        return back()->with('success', 'Pengaturan printer disimpan.');
    }

    public function loyalty()
    {
        return Inertia::render('Dashboard/Settings/Loyalty', [
            'settings' => $this->loyaltyService->settingsPayload(),
        ]);
    }

    public function updateLoyalty(Request $request)
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
            return back()
                ->withErrors([
                    'tiers' => 'Threshold tier harus berurutan dari Regular ke Platinum.',
                ])
                ->withInput();
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

        return back()->with('success', 'Pengaturan loyalty berhasil disimpan');
    }
}
