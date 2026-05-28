<?php

declare(strict_types=1);

namespace App\Services\PaymentSettings;

use App\Models\PaymentSetting;
use App\Services\AuditLogService;

class PaymentSettingPayloadService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function settingPayload(PaymentSetting $setting): array
    {
        return [
            'default_gateway' => $setting->default_gateway,
            'bank_transfer_enabled' => (bool) $setting->bank_transfer_enabled,
            'midtrans_enabled' => (bool) $setting->midtrans_enabled,
            'midtrans_client_key' => $setting->midtrans_client_key,
            'midtrans_production' => (bool) $setting->midtrans_production,
            'xendit_enabled' => (bool) $setting->xendit_enabled,
            'xendit_public_key' => $setting->xendit_public_key,
            'xendit_production' => (bool) $setting->xendit_production,
        ];
    }

    public function supportedGateways(): array
    {
        return [
            ['value' => 'cash', 'label' => 'Tunai'],
            ['value' => PaymentSetting::GATEWAY_BANK_TRANSFER, 'label' => 'Transfer Bank'],
            ['value' => PaymentSetting::GATEWAY_MIDTRANS, 'label' => 'Midtrans'],
            ['value' => PaymentSetting::GATEWAY_XENDIT, 'label' => 'Xendit'],
        ];
    }

    public function webhookWarnings(PaymentSetting $setting): array
    {
        $appUrl = (string) config('app.url');
        $warnings = [];

        if (blank($appUrl)) {
            $warnings[] = 'APP_URL belum diatur. Webhook URL yang dihasilkan bisa tidak valid untuk Midtrans/Xendit.';
        } elseif ($this->isLocalAppUrl($appUrl)) {
            $warnings[] = 'APP_URL masih mengarah ke localhost atau 127.0.0.1. Payment gateway membutuhkan URL publik yang bisa diakses dari internet.';
        }

        if ($setting->xendit_enabled && ! $setting->secretConfigured('xendit_callback_token')) {
            $warnings[] = 'Xendit aktif tetapi callback token belum diisi. Webhook Xendit akan ditolak sampai token tersedia.';
        }

        return $warnings;
    }

    public function beforeAuditPayload(PaymentSetting $setting): array
    {
        return [
            'default_gateway' => $setting->default_gateway,
            'bank_transfer_enabled' => (bool) $setting->bank_transfer_enabled,
            'midtrans_enabled' => (bool) $setting->midtrans_enabled,
            'midtrans_production' => (bool) $setting->midtrans_production,
            'xendit_enabled' => (bool) $setting->xendit_enabled,
            'xendit_production' => (bool) $setting->xendit_production,
            'midtrans_server_key' => filled($setting->midtrans_server_key) ? 'configured' : 'empty',
            'midtrans_client_key' => filled($setting->midtrans_client_key) ? 'configured' : 'empty',
            'xendit_secret_key' => filled($setting->xendit_secret_key) ? 'configured' : 'empty',
            'xendit_public_key' => filled($setting->xendit_public_key) ? 'configured' : 'empty',
            'xendit_callback_token' => filled($setting->xendit_callback_token) ? 'configured' : 'empty',
        ];
    }

    public function afterAuditPayload(PaymentSetting $before, PaymentSetting $after): array
    {
        return [
            'default_gateway' => $after->default_gateway,
            'bank_transfer_enabled' => (bool) $after->bank_transfer_enabled,
            'midtrans_enabled' => (bool) $after->midtrans_enabled,
            'midtrans_production' => (bool) $after->midtrans_production,
            'xendit_enabled' => (bool) $after->xendit_enabled,
            'xendit_production' => (bool) $after->xendit_production,
            'midtrans_server_key' => $this->auditLogService->credentialState($before->midtrans_server_key, $after->midtrans_server_key),
            'midtrans_client_key' => $this->auditLogService->credentialState($before->midtrans_client_key, $after->midtrans_client_key),
            'xendit_secret_key' => $this->auditLogService->credentialState($before->xendit_secret_key, $after->xendit_secret_key),
            'xendit_public_key' => $this->auditLogService->credentialState($before->xendit_public_key, $after->xendit_public_key),
            'xendit_callback_token' => $this->auditLogService->credentialState($before->xendit_callback_token, $after->xendit_callback_token),
        ];
    }

    public function environmentManagedSecretKeys(PaymentSetting $setting): array
    {
        return collect($setting->paymentSettingSources())
            ->filter(fn (array $source) => $source['source'] === 'env')
            ->keys()
            ->values()
            ->all();
    }

    public function hasEnvironmentManagedSecrets(PaymentSetting $setting): bool
    {
        return $this->environmentManagedSecretKeys($setting) !== [];
    }

    private function isLocalAppUrl(string $appUrl): bool
    {
        $host = parse_url($appUrl, PHP_URL_HOST);

        return in_array($host, ['localhost', '127.0.0.1'], true)
            || str_ends_with((string) $host, '.test');
    }
}
