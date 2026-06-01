<?php

declare(strict_types=1);

namespace App\Services\Payments\Webhooks;

use App\DTOs\Payments\MidtransWebhookDto;
use App\DTOs\Payments\PaymentWebhookResultDto;
use App\Models\PaymentSetting;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HandleMidtransWebhookService
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly PaymentWebhookStatusMapper $statusMapper,
        private readonly PaymentWebhookAuditService $auditService
    ) {}

    public function execute(MidtransWebhookDto $payload): PaymentWebhookResultDto
    {
        try {
            $setting = PaymentSetting::query()->first();
            $serverKey = $setting?->resolvedSecret('midtrans_server_key');

            if (! $setting?->midtrans_enabled || blank($serverKey)) {
                return PaymentWebhookResultDto::error('Midtrans not configured', 400);
            }

            if (! $this->hasValidSignature($payload, $serverKey)) {
                Log::warning('Midtrans Webhook: Invalid signature', [
                    'provider' => PaymentSetting::GATEWAY_MIDTRANS,
                    'order_id' => $payload->orderId,
                    'verification_result' => 'invalid',
                    'error_category' => 'invalid_signature',
                ]);

                return PaymentWebhookResultDto::error('Invalid signature', 403);
            }

            return DB::transaction(function () use ($payload) {
                $transaction = $this->transactionRepository->findByInvoiceForUpdate($payload->orderId);

                if (! $transaction) {
                    Log::warning('Midtrans Webhook: Transaction not found', [
                        'provider' => PaymentSetting::GATEWAY_MIDTRANS,
                        'order_id' => $payload->orderId,
                        'verification_result' => 'valid',
                        'error_category' => 'transaction_not_found',
                    ]);

                    return PaymentWebhookResultDto::error('Transaction not found', 404);
                }

                $paymentStatus = $this->statusMapper
                    ->fromMidtrans($payload->transactionStatus, $payload->fraudStatus)
                    ->value;
                $beforeStatus = $transaction->payment_status;
                $beforeReference = $transaction->payment_reference;

                $transaction = $this->transactionRepository->updatePaymentState(
                    $transaction,
                    $paymentStatus,
                    $payload->transactionId
                );
                $this->auditService->logTransactionUpdate(
                    $transaction,
                    PaymentSetting::GATEWAY_MIDTRANS,
                    $beforeStatus,
                    $beforeReference
                );

                Log::info('Midtrans Webhook: Transaction updated', [
                    'provider' => PaymentSetting::GATEWAY_MIDTRANS,
                    'order_id' => $payload->orderId,
                    'payment_reference' => $payload->transactionId,
                    'normalized_status' => $paymentStatus,
                    'verification_result' => 'valid',
                ]);

                return PaymentWebhookResultDto::success();
            });
        } catch (Throwable $throwable) {
            Log::error('Midtrans Webhook Error', [
                'provider' => PaymentSetting::GATEWAY_MIDTRANS,
                'order_id' => $payload->orderId,
                'verification_result' => 'unknown',
                'error_category' => 'exception',
                'message' => $throwable->getMessage(),
            ]);

            return PaymentWebhookResultDto::error('Internal Server Error', 500);
        }
    }

    private function hasValidSignature(MidtransWebhookDto $payload, string $serverKey): bool
    {
        $expectedSignature = hash(
            'sha512',
            $payload->orderId.$payload->statusCode.$payload->grossAmount.$serverKey
        );

        return hash_equals($expectedSignature, $payload->signatureKey);
    }
}
