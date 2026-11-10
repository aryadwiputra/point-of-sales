<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\PaymentSettingController as AppsPaymentSettingController;
use App\Http\Traits\ApiResponder;
use App\Models\PaymentSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentSettingController extends AppsPaymentSettingController
{
    use ApiResponder;

    /**
     * GET /api/v1/settings/payments
     */
    public function editPaymentSettings(): JsonResponse
    {
        $setting = $this->setting();

        $appUrl = (string) config('app.url');
        $webhookWarnings = [];

        if (blank($appUrl)) {
            $webhookWarnings[] = 'APP_URL belum diatur. Webhook URL yang dihasilkan bisa tidak valid untuk Midtrans/Xendit.';
        } elseif ($this->isLocalAppUrl($appUrl)) {
            $webhookWarnings[] = 'APP_URL masih mengarah ke localhost atau 127.0.0.1. Payment gateway membutuhkan URL publik yang bisa diakses dari internet.';
        }

        if ($setting->xendit_enabled && ! $setting->secretConfigured('xendit_callback_token')) {
            $webhookWarnings[] = 'Xendit aktif tetapi callback token belum diisi. Webhook Xendit akan ditolak sampai token tersedia.';
        }

        return $this->ok([
            'setting' => [
                'default_gateway' => $setting->default_gateway,
                'bank_transfer_enabled' => (bool) $setting->bank_transfer_enabled,
                'midtrans_enabled' => (bool) $setting->midtrans_enabled,
                'midtrans_client_key' => $setting->midtrans_client_key,
                'midtrans_production' => (bool) $setting->midtrans_production,
                'xendit_enabled' => (bool) $setting->xendit_enabled,
                'xendit_public_key' => $setting->xendit_public_key,
                'xendit_production' => (bool) $setting->xendit_production,
            ],
            'payment_setting_sources' => $setting->paymentSettingSources(),
            'supported_gateways' => [
                ['value' => 'cash', 'label' => 'Tunai'],
                ['value' => PaymentSetting::GATEWAY_BANK_TRANSFER, 'label' => 'Transfer Bank'],
                ['value' => PaymentSetting::GATEWAY_MIDTRANS, 'label' => 'Midtrans'],
                ['value' => PaymentSetting::GATEWAY_XENDIT, 'label' => 'Xendit'],
            ],
            'webhook_urls' => [
                'midtrans' => route('webhooks.midtrans'),
                'xendit' => route('webhooks.xendit'),
            ],
            'webhook_warnings' => $webhookWarnings,
        ]);
    }

    /**
     * PUT /api/v1/settings/payments
     */
    public function updatePaymentSettings(Request $request): JsonResponse
    {
        $setting = $this->setting();
        $beforeState = $setting->replicate();

        $data = $request->validate([
            'default_gateway' => [
                'required',
                Rule::in(['cash', PaymentSetting::GATEWAY_BANK_TRANSFER, PaymentSetting::GATEWAY_MIDTRANS, PaymentSetting::GATEWAY_XENDIT]),
            ],
            'bank_transfer_enabled' => ['boolean'],
            'midtrans_enabled' => ['boolean'],
            'midtrans_server_key' => ['nullable', 'string'],
            'midtrans_client_key' => ['nullable', 'string'],
            'midtrans_production' => ['boolean'],
            'xendit_enabled' => ['boolean'],
            'xendit_secret_key' => ['nullable', 'string'],
            'xendit_public_key' => ['nullable', 'string'],
            'xendit_callback_token' => ['nullable', 'string', 'max:255'],
            'xendit_production' => ['boolean'],
        ]);

        $midtransEnabled = (bool) ($data['midtrans_enabled'] ?? false);
        $xenditEnabled = (bool) ($data['xendit_enabled'] ?? false);
        $resolvedMidtransServerKey = $setting->secretManagedByEnvironment('midtrans_server_key')
            ? $setting->resolvedSecret('midtrans_server_key')
            : (($data['midtrans_server_key'] ?? null) ?: $setting->getAttributeValue('midtrans_server_key'));
        $resolvedXenditSecretKey = $setting->secretManagedByEnvironment('xendit_secret_key')
            ? $setting->resolvedSecret('xendit_secret_key')
            : (($data['xendit_secret_key'] ?? null) ?: $setting->getAttributeValue('xendit_secret_key'));
        $resolvedXenditCallbackToken = $setting->secretManagedByEnvironment('xendit_callback_token')
            ? $setting->resolvedSecret('xendit_callback_token')
            : (($data['xendit_callback_token'] ?? null) ?: $setting->getAttributeValue('xendit_callback_token'));

        if ($midtransEnabled && (blank($resolvedMidtransServerKey) || empty($data['midtrans_client_key'] ?? null))) {
            throw ValidationException::withMessages([
                'midtrans_server_key' => 'Server key dan Client key Midtrans wajib diisi saat mengaktifkan Midtrans.',
            ]);
        }

        if ($xenditEnabled && blank($resolvedXenditSecretKey)) {
            throw ValidationException::withMessages([
                'xendit_secret_key' => 'Secret key Xendit wajib diisi saat mengaktifkan Xendit.',
            ]);
        }

        if ($xenditEnabled && blank($resolvedXenditCallbackToken)) {
            throw ValidationException::withMessages([
                'xendit_callback_token' => 'Callback token Xendit wajib diisi saat mengaktifkan Xendit.',
            ]);
        }

        if (
            $data['default_gateway'] !== 'cash'
            && ! (($data['default_gateway'] === PaymentSetting::GATEWAY_MIDTRANS && $midtransEnabled)
                || ($data['default_gateway'] === PaymentSetting::GATEWAY_XENDIT && $xenditEnabled))
        ) {
            throw ValidationException::withMessages([
                'default_gateway' => 'Gateway default harus dalam kondisi aktif.',
            ]);
        }

        $setting->update([
            'default_gateway' => $data['default_gateway'],
            'bank_transfer_enabled' => (bool) ($data['bank_transfer_enabled'] ?? false),
            'midtrans_enabled' => $midtransEnabled,
            'midtrans_server_key' => $setting->secretManagedByEnvironment('midtrans_server_key')
                ? $setting->getRawOriginal('midtrans_server_key')
                : (($data['midtrans_server_key'] ?? null) ?: $setting->getAttributeValue('midtrans_server_key')),
            'midtrans_client_key' => $data['midtrans_client_key'] ?? $setting->getAttributeValue('midtrans_client_key'),
            'midtrans_production' => (bool) ($data['midtrans_production'] ?? false),
            'xendit_enabled' => $xenditEnabled,
            'xendit_secret_key' => $setting->secretManagedByEnvironment('xendit_secret_key')
                ? $setting->getRawOriginal('xendit_secret_key')
                : (($data['xendit_secret_key'] ?? null) ?: $setting->getAttributeValue('xendit_secret_key')),
            'xendit_public_key' => $data['xendit_public_key'] ?? $setting->getAttributeValue('xendit_public_key'),
            'xendit_callback_token' => $setting->secretManagedByEnvironment('xendit_callback_token')
                ? $setting->getRawOriginal('xendit_callback_token')
                : (($data['xendit_callback_token'] ?? null) ?: $setting->getAttributeValue('xendit_callback_token')),
            'xendit_production' => (bool) ($data['xendit_production'] ?? false),
        ]);

        $this->auditLogService->log(
            event: 'payment.setting.updated',
            module: 'payment_settings',
            auditable: $setting,
            description: 'Konfigurasi payment gateway diperbarui.',
            before: [
                'default_gateway' => $beforeState->default_gateway,
                'bank_transfer_enabled' => (bool) $beforeState->bank_transfer_enabled,
                'midtrans_enabled' => (bool) $beforeState->midtrans_enabled,
                'midtrans_production' => (bool) $beforeState->midtrans_production,
                'xendit_enabled' => (bool) $beforeState->xendit_enabled,
                'xendit_production' => (bool) $beforeState->xendit_production,
                'midtrans_server_key' => filled($beforeState->midtrans_server_key) ? 'configured' : 'empty',
                'midtrans_client_key' => filled($beforeState->midtrans_client_key) ? 'configured' : 'empty',
                'xendit_secret_key' => filled($beforeState->xendit_secret_key) ? 'configured' : 'empty',
                'xendit_public_key' => filled($beforeState->xendit_public_key) ? 'configured' : 'empty',
                'xendit_callback_token' => filled($beforeState->xendit_callback_token) ? 'configured' : 'empty',
            ],
            after: [
                'default_gateway' => $setting->default_gateway,
                'bank_transfer_enabled' => (bool) $setting->bank_transfer_enabled,
                'midtrans_enabled' => (bool) $setting->midtrans_enabled,
                'midtrans_production' => (bool) $setting->midtrans_production,
                'xendit_enabled' => (bool) $setting->xendit_enabled,
                'xendit_production' => (bool) $setting->xendit_production,
                'midtrans_server_key' => $this->auditLogService->credentialState($beforeState->midtrans_server_key, $setting->midtrans_server_key),
                'midtrans_client_key' => $this->auditLogService->credentialState($beforeState->midtrans_client_key, $setting->midtrans_client_key),
                'xendit_secret_key' => $this->auditLogService->credentialState($beforeState->xendit_secret_key, $setting->xendit_secret_key),
                'xendit_public_key' => $this->auditLogService->credentialState($beforeState->xendit_public_key, $setting->xendit_public_key),
                'xendit_callback_token' => $this->auditLogService->credentialState($beforeState->xendit_callback_token, $setting->xendit_callback_token),
            ],
        );

        return $this->ok(['setting' => [
            'default_gateway' => $setting->default_gateway,
            'bank_transfer_enabled' => (bool) $setting->bank_transfer_enabled,
            'midtrans_enabled' => (bool) $setting->midtrans_enabled,
            'midtrans_production' => (bool) $setting->midtrans_production,
            'xendit_enabled' => (bool) $setting->xendit_enabled,
            'xendit_production' => (bool) $setting->xendit_production,
        ]]);
    }
}
