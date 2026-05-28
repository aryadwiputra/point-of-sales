<?php

declare(strict_types=1);

namespace App\Services\PaymentSettings;

use App\Models\PaymentSetting;
use App\Services\AuditLogService;

class PaymentSettingEditQueryService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly PaymentSettingPayloadService $payloadService
    ) {}

    public function execute(): array
    {
        $setting = PaymentSetting::firstOrCreate([], [
            'default_gateway' => 'cash',
        ]);

        if ($this->payloadService->hasEnvironmentManagedSecrets($setting)) {
            $this->auditLogService->log(
                event: 'security.payment_secret_source_overridden',
                module: 'security',
                auditable: $setting,
                description: 'Konfigurasi payment memakai env override untuk secret sensitif.',
                meta: [
                    'severity' => 'info',
                    'sources' => $this->payloadService->environmentManagedSecretKeys($setting),
                ],
            );
        }

        return [
            'setting' => $this->payloadService->settingPayload($setting),
            'paymentSettingSources' => $setting->paymentSettingSources(),
            'supportedGateways' => $this->payloadService->supportedGateways(),
            'webhookUrls' => [
                'midtrans' => route('webhooks.midtrans'),
                'xendit' => route('webhooks.xendit'),
            ],
            'webhookWarnings' => $this->payloadService->webhookWarnings($setting),
        ];
    }
}
