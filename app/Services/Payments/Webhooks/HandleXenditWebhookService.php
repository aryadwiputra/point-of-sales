<?php

declare(strict_types=1);

namespace App\Services\Payments\Webhooks;

use App\DTOs\Payments\PaymentWebhookResultDto;
use App\DTOs\Payments\XenditWebhookDto;
use App\Models\PaymentSetting;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HandleXenditWebhookService
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly PaymentWebhookStatusMapper $statusMapper,
        private readonly PaymentWebhookAuditService $auditService
    ) {}

    public function execute(XenditWebhookDto $payload): PaymentWebhookResultDto
    {
        try {
            $setting = PaymentSetting::query()->first();

            if (! $setting?->xendit_enabled) {
                return PaymentWebhookResultDto::error('Xendit not configured', 400);
            }

            $expectedToken = $setting->resolvedSecret('xendit_callback_token');

            if (blank($expectedToken)) {
                Log::warning('Xendit Webhook: Callback token is not configured.', [
                    'provider' => PaymentSetting::GATEWAY_XENDIT,
                    'external_id' => $payload->externalId,
                    'verification_result' => 'misconfigured',
                    'error_category' => 'missing_callback_token',
                ]);

                return PaymentWebhookResultDto::error('Xendit callback token is not configured', 400);
            }

            if (! is_string($payload->callbackToken) || ! hash_equals($expectedToken, $payload->callbackToken)) {
                Log::warning('Xendit Webhook: Invalid callback token', [
                    'provider' => PaymentSetting::GATEWAY_XENDIT,
                    'external_id' => $payload->externalId,
                    'verification_result' => 'invalid',
                    'error_category' => 'invalid_callback_token',
                ]);

                return PaymentWebhookResultDto::error('Invalid callback token', 403);
            }

            return DB::transaction(function () use ($payload) {
                $transaction = $this->transactionRepository->findByInvoiceForUpdate($payload->externalId);

                if (! $transaction) {
                    Log::warning('Xendit Webhook: Transaction not found', [
                        'provider' => PaymentSetting::GATEWAY_XENDIT,
                        'external_id' => $payload->externalId,
                        'verification_result' => 'valid',
                        'error_category' => 'transaction_not_found',
                    ]);

                    return PaymentWebhookResultDto::error('Transaction not found', 404);
                }

                $paymentStatus = $this->statusMapper
                    ->fromXendit($payload->status)
                    ->value;
                $beforeStatus = $transaction->payment_status;
                $beforeReference = $transaction->payment_reference;

                $transaction = $this->transactionRepository->updatePaymentState(
                    $transaction,
                    $paymentStatus,
                    $payload->paymentId
                );
                $this->auditService->logTransactionUpdate(
                    $transaction,
                    PaymentSetting::GATEWAY_XENDIT,
                    $beforeStatus,
                    $beforeReference
                );

                Log::info('Xendit Webhook: Transaction updated', [
                    'provider' => PaymentSetting::GATEWAY_XENDIT,
                    'external_id' => $payload->externalId,
                    'payment_reference' => $payload->paymentId,
                    'normalized_status' => $paymentStatus,
                    'verification_result' => 'valid',
                ]);

                return PaymentWebhookResultDto::success();
            });
        } catch (Throwable $throwable) {
            Log::error('Xendit Webhook Error', [
                'provider' => PaymentSetting::GATEWAY_XENDIT,
                'external_id' => $payload->externalId,
                'verification_result' => 'unknown',
                'error_category' => 'exception',
                'message' => $throwable->getMessage(),
            ]);

            return PaymentWebhookResultDto::error('Internal Server Error', 500);
        }
    }
}
