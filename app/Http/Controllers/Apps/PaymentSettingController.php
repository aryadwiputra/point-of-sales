<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PaymentSettingController extends Controller
{
    public function edit()
    {
        $setting = PaymentSetting::firstOrCreate([], [
            'default_gateway' => 'cash',
        ]);

        return Inertia::render('Dashboard/Settings/Payment', [
            'setting' => $setting,
            'supportedGateways' => [
                ['value' => 'cash', 'label' => 'Tunai'],
                ['value' => PaymentSetting::GATEWAY_MIDTRANS, 'label' => 'Midtrans'],
                ['value' => PaymentSetting::GATEWAY_XENDIT, 'label' => 'Xendit'],
            ],
        ]);
    }

    public function update(Request $request)
    {
        $setting = PaymentSetting::firstOrCreate([], [
            'default_gateway' => 'cash',
        ]);

        $data = $request->validate([
            'default_gateway' => [
                'required',
                Rule::in(['cash', PaymentSetting::GATEWAY_MIDTRANS, PaymentSetting::GATEWAY_XENDIT]),
            ],
            'midtrans_enabled' => ['boolean'],
            'midtrans_server_key' => ['nullable', 'string'],
            'midtrans_client_key' => ['nullable', 'string'],
            'midtrans_production' => ['boolean'],
            'xendit_enabled' => ['boolean'],
            'xendit_secret_key' => ['nullable', 'string'],
            'xendit_public_key' => ['nullable', 'string'],
            'xendit_production' => ['boolean'],
        ]);

        $midtransEnabled = (bool) ($data['midtrans_enabled'] ?? false);
        $xenditEnabled = (bool) ($data['xendit_enabled'] ?? false);

        if ($midtransEnabled && (empty($data['midtrans_server_key']) || empty($data['midtrans_client_key']))) {
            return back()->withErrors([
                'midtrans_server_key' => 'Server key dan Client key Midtrans wajib diisi saat mengaktifkan Midtrans.',
            ])->withInput();
        }

        if ($xenditEnabled && empty($data['xendit_secret_key'])) {
            return back()->withErrors([
                'xendit_secret_key' => 'Secret key Xendit wajib diisi saat mengaktifkan Xendit.',
            ])->withInput();
        }

        if (
            $data['default_gateway'] !== 'cash'
            && !(($data['default_gateway'] === PaymentSetting::GATEWAY_MIDTRANS && $midtransEnabled)
                || ($data['default_gateway'] === PaymentSetting::GATEWAY_XENDIT && $xenditEnabled))
        ) {
            return back()->withErrors([
                'default_gateway' => 'Gateway default harus dalam kondisi aktif.',
            ])->withInput();
        }

        $setting->update([
            'default_gateway' => $data['default_gateway'],
            'midtrans_enabled' => $midtransEnabled,
            'midtrans_server_key' => $data['midtrans_server_key'],
            'midtrans_client_key' => $data['midtrans_client_key'],
            'midtrans_production' => (bool) ($data['midtrans_production'] ?? false),
            'xendit_enabled' => $xenditEnabled,
            'xendit_secret_key' => $data['xendit_secret_key'],
            'xendit_public_key' => $data['xendit_public_key'],
            'xendit_production' => (bool) ($data['xendit_production'] ?? false),
        ]);

        return redirect()
            ->route('settings.payments.edit')
            ->with('success', 'Konfigurasi payment gateway berhasil disimpan.');
    }
}
