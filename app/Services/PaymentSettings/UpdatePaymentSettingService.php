<?php

declare(strict_types=1);

namespace App\Services\PaymentSettings;

use App\Models\PaymentSetting;
use App\Services\AuditLogService;
use Illuminate\Validation\ValidationException;

class UpdatePaymentSettingService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly PaymentSettingPayloadService $payloadService
    ) {}

    public function execute(array $data): PaymentSetting
    {
        $setting = PaymentSetting::firstOrCreate([], [
            'default_gateway' => 'cash',
        ]);
        $beforeState = $setting->replicate();

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

        $this->ensureGatewayConfigurationIsValid(
            data: $data,
            midtransEnabled: $midtransEnabled,
            xenditEnabled: $xenditEnabled,
            resolvedMidtransServerKey: $resolvedMidtransServerKey,
            resolvedXenditSecretKey: $resolvedXenditSecretKey,
            resolvedXenditCallbackToken: $resolvedXenditCallbackToken
        );

        $setting->update([
            'default_gateway' => $data['default_gateway'],
            'bank_transfer_enabled' => (bool) ($data['bank_transfer_enabled'] ?? false),
            'midtrans_enabled' => $midtransEnabled,
            'midtrans_server_key' => $setting->secretManagedByEnvironment('midtrans_server_key')
                ? $setting->getRawOriginal('midtrans_server_key')
                : (($data['midtrans_server_key'] ?? null) ?: $setting->getAttributeValue('midtrans_server_key')),
            'midtrans_client_key' => $data['midtrans_client_key'] ?? null,
            'midtrans_production' => (bool) ($data['midtrans_production'] ?? false),
            'xendit_enabled' => $xenditEnabled,
            'xendit_secret_key' => $setting->secretManagedByEnvironment('xendit_secret_key')
                ? $setting->getRawOriginal('xendit_secret_key')
                : (($data['xendit_secret_key'] ?? null) ?: $setting->getAttributeValue('xendit_secret_key')),
            'xendit_public_key' => $data['xendit_public_key'] ?? null,
            'xendit_callback_token' => $setting->secretManagedByEnvironment('xendit_callback_token')
                ? $setting->getRawOriginal('xendit_callback_token')
                : (($data['xendit_callback_token'] ?? null) ?: $setting->getAttributeValue('xendit_callback_token')),
            'xendit_production' => (bool) ($data['xendit_production'] ?? false),
        ]);

        $setting = $setting->fresh();

        $this->auditLogService->log(
            event: 'payment.setting.updated',
            module: 'payment_settings',
            auditable: $setting,
            description: 'Konfigurasi payment gateway diperbarui.',
            before: $this->payloadService->beforeAuditPayload($beforeState),
            after: $this->payloadService->afterAuditPayload($beforeState, $setting),
        );

        if ($this->payloadService->hasEnvironmentManagedSecrets($setting)) {
            $this->auditLogService->log(
                event: 'security.payment_secret_source_overridden',
                module: 'security',
                auditable: $setting,
                description: 'Perubahan payment settings tetap tunduk pada env override untuk secret sensitif.',
                meta: [
                    'severity' => 'info',
                    'sources' => $this->payloadService->environmentManagedSecretKeys($setting),
                ],
            );
        }

        return $setting;
    }

    private function ensureGatewayConfigurationIsValid(
        array $data,
        bool $midtransEnabled,
        bool $xenditEnabled,
        ?string $resolvedMidtransServerKey,
        ?string $resolvedXenditSecretKey,
        ?string $resolvedXenditCallbackToken
    ): void {
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
    }
}
