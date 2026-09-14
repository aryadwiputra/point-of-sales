<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use App\Models\Transaction;
use App\Models\TransactionTender;
use App\Services\TransactionTenderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    /**
     * Handle Midtrans notification webhook
     * URL: POST /api/webhooks/midtrans
     */
    public function midtrans(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            $transaction = Transaction::where('invoice', $this->baseInvoice($orderId))->with('warehouse.outlet')->first();
            $paymentSetting = PaymentSetting::forOutlet($transaction?->warehouse?->outlet);

            if (! $paymentSetting || ! $paymentSetting->midtrans_enabled) {
                return response()->json(['status' => 'error', 'message' => 'Midtrans not configured'], 400);
            }

            // Get notification data
            $statusCode = $request->input('status_code');
            $grossAmount = $request->input('gross_amount');
            $serverKey = $paymentSetting->resolvedSecret('midtrans_server_key');

            // Verify signature
            $signatureKey = $request->input('signature_key');
            $expectedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

            if ($signatureKey !== $expectedSignature) {
                Log::warning('Midtrans Webhook: Invalid signature', [
                    'provider' => 'midtrans',
                    'order_id' => $orderId,
                    'verification_result' => 'invalid',
                    'error_category' => 'invalid_signature',
                ]);

                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 403);
            }

            // Split gateway tenders use invoice-method as the provider order id.
            $invoice = $this->baseInvoice($orderId);
            $transaction ??= Transaction::where('invoice', $invoice)->with('warehouse.outlet')->first();

            if (! $transaction) {
                Log::warning('Midtrans Webhook: Transaction not found', [
                    'provider' => 'midtrans',
                    'order_id' => $orderId,
                    'verification_result' => 'valid',
                    'error_category' => 'transaction_not_found',
                ]);

                return response()->json(['status' => 'error', 'message' => 'Transaction not found'], 404);
            }

            // Map Midtrans status to our status
            $transactionStatus = $request->input('transaction_status');
            $fraudStatus = $request->input('fraud_status');

            $newStatus = $this->mapMidtransStatus($transactionStatus, $fraudStatus);

            // Split payments: tender-level update when order_id targets one (invoice-{method})
            $tender = TransactionTender::where('transaction_id', $transaction->id)
                ->whereIn('method', [TransactionTender::METHOD_MIDTRANS, TransactionTender::METHOD_QRIS])
                ->get()
                ->first(fn (TransactionTender $t) => $orderId === $t->order_reference);

            if ($tender) {
                $tender->update([
                    'payment_status' => $newStatus,
                    'payment_reference' => $request->input('transaction_id') ?: $tender->payment_reference,
                    'paid_at' => $newStatus === 'paid' ? now() : $tender->paid_at,
                ]);
                $transaction->update(['payment_status' => app(TransactionTenderService::class)->aggregateStatus(
                    $transaction->tenders()->get()->map(fn (TransactionTender $t) => ['method' => $t->method, 'payment_status' => $t->payment_status])->all()
                )]);
            } else {
                $transaction->update([
                    'payment_status' => $newStatus,
                    'payment_reference' => $request->input('transaction_id') ?: $transaction->payment_reference,
                ]);
            }

            Log::info('Midtrans Webhook: Transaction updated', [
                'provider' => 'midtrans',
                'order_id' => $orderId,
                'payment_reference' => $request->input('transaction_id'),
                'normalized_status' => $newStatus,
                'verification_result' => 'valid',
            ]);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('Midtrans Webhook Error', [
                'provider' => 'midtrans',
                'order_id' => $request->input('order_id'),
                'verification_result' => 'unknown',
                'error_category' => 'exception',
                'message' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle Xendit notification webhook
     * URL: POST /api/webhooks/xendit
     */
    public function xendit(Request $request)
    {
        try {
            $externalId = $request->input('external_id');
            $transaction = Transaction::where('invoice', $this->baseInvoice($externalId))->with('warehouse.outlet')->first();
            $paymentSetting = PaymentSetting::forOutlet($transaction?->warehouse?->outlet);

            if (! $paymentSetting || ! $paymentSetting->xendit_enabled) {
                return response()->json(['status' => 'error', 'message' => 'Xendit not configured'], 400);
            }

            $callbackToken = $request->header('X-CALLBACK-TOKEN');
            $expectedToken = $paymentSetting->resolvedSecret('xendit_callback_token');

            if (blank($expectedToken)) {
                Log::warning('Xendit Webhook: Callback token is not configured.', [
                    'provider' => 'xendit',
                    'external_id' => $request->input('external_id'),
                    'verification_result' => 'misconfigured',
                    'error_category' => 'missing_callback_token',
                ]);

                return response()->json(['status' => 'error', 'message' => 'Xendit callback token is not configured'], 400);
            }

            if (! is_string($callbackToken) || ! hash_equals($expectedToken, $callbackToken)) {
                Log::warning('Xendit Webhook: Invalid callback token', [
                    'provider' => 'xendit',
                    'external_id' => $request->input('external_id'),
                    'verification_result' => 'invalid',
                    'error_category' => 'invalid_callback_token',
                ]);

                return response()->json(['status' => 'error', 'message' => 'Invalid callback token'], 403);
            }

            $status = $request->input('status');
            $paymentId = $request->input('id');

            if (blank($externalId) || blank($status) || blank($paymentId)) {
                return response()->json(['status' => 'error', 'message' => 'Invalid payload'], 422);
            }

            // Split gateway tenders use invoice-method as the provider external id.
            $invoice = $this->baseInvoice($externalId);
            $transaction ??= Transaction::where('invoice', $invoice)->with('warehouse.outlet')->first();

            if (! $transaction) {
                Log::warning('Xendit Webhook: Transaction not found', [
                    'provider' => 'xendit',
                    'external_id' => $externalId,
                    'verification_result' => 'valid',
                    'error_category' => 'transaction_not_found',
                ]);

                return response()->json(['status' => 'error', 'message' => 'Transaction not found'], 404);
            }

            // Map Xendit status to our status
            $newStatus = $this->mapXenditStatus($status);

            // Split payments: tender-level update when external_id targets one (invoice-{method})
            $tender = TransactionTender::where('transaction_id', $transaction->id)
                ->whereIn('method', [TransactionTender::METHOD_XENDIT, TransactionTender::METHOD_QRIS])
                ->get()
                ->first(fn (TransactionTender $t) => $externalId === $t->order_reference);

            if ($tender) {
                $tender->update([
                    'payment_status' => $newStatus,
                    'payment_reference' => $paymentId ?: $tender->payment_reference,
                    'paid_at' => $newStatus === 'paid' ? now() : $tender->paid_at,
                ]);
                $transaction->update(['payment_status' => app(TransactionTenderService::class)->aggregateStatus(
                    $transaction->tenders()->get()->map(fn (TransactionTender $t) => ['method' => $t->method, 'payment_status' => $t->payment_status])->all()
                )]);
            } else {
                $transaction->update([
                    'payment_status' => $newStatus,
                    'payment_reference' => $paymentId ?: $transaction->payment_reference,
                ]);
            }

            Log::info('Xendit Webhook: Transaction updated', [
                'provider' => 'xendit',
                'external_id' => $externalId,
                'payment_reference' => $paymentId,
                'normalized_status' => $newStatus,
                'verification_result' => 'valid',
            ]);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('Xendit Webhook Error', [
                'provider' => 'xendit',
                'external_id' => $request->input('external_id'),
                'verification_result' => 'unknown',
                'error_category' => 'exception',
                'message' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Map Midtrans transaction status to our payment status
     */
    private function mapMidtransStatus(string $transactionStatus, ?string $fraudStatus = null): string
    {
        // Handle fraud status first
        if ($fraudStatus === 'challenge' || $fraudStatus === 'deny') {
            return 'failed';
        }

        return match ($transactionStatus) {
            'capture', 'settlement' => 'paid',
            'pending' => 'pending',
            'deny', 'cancel', 'expire' => 'failed',
            default => 'pending',
        };
    }

    /**
     * Map Xendit invoice status to our payment status
     */
    private function mapXenditStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PAID', 'SETTLED' => 'paid',
            'PENDING' => 'pending',
            'EXPIRED', 'FAILED' => 'failed',
            default => 'pending',
        };
    }

    private function baseInvoice(string $providerId): string
    {
        foreach (TransactionTenderService::GATEWAY_METHODS as $method) {
            $suffix = '-'.$method;
            if (str_ends_with($providerId, $suffix)) {
                return substr($providerId, 0, -strlen($suffix));
            }
        }

        return $providerId;
    }
}
