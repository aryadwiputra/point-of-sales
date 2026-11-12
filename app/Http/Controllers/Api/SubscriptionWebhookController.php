<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionWebhookController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptionService) {}

    /**
     * Midtrans notification for platform subscription invoices (order id `SUB-{id}`).
     * URL: POST /api/webhooks/subscription/midtrans
     */
    public function midtrans(Request $request)
    {
        $orderId = (string) $request->input('order_id');
        $serverKey = (string) config('services.midtrans.server_key');

        if ($serverKey === '') {
            Log::warning('Subscription Midtrans Webhook: server key not configured', ['order_id' => $orderId]);

            return response()->json(['status' => 'error', 'message' => 'Midtrans not configured'], 400);
        }

        $statusCode = (string) $request->input('status_code');
        $grossAmount = (string) $request->input('gross_amount');
        $signatureKey = $request->input('signature_key');
        $expectedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        if (! is_string($signatureKey) || ! hash_equals($expectedSignature, $signatureKey)) {
            Log::warning('Subscription Midtrans Webhook: invalid signature', ['order_id' => $orderId]);

            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 403);
        }

        $subscription = $this->subscriptionService->findByOrderId($orderId);

        if (! $subscription) {
            return response()->json(['status' => 'error', 'message' => 'Subscription not found'], 404);
        }

        if ($subscription->payment_status === 'paid') {
            return response()->json(['status' => 'success']);
        }

        $transactionStatus = (string) $request->input('transaction_status');
        $fraudStatus = (string) $request->input('fraud_status');

        $paid = $transactionStatus === 'settlement'
            || ($transactionStatus === 'capture' && $fraudStatus === 'accept');

        if ($paid) {
            $this->subscriptionService->activate($subscription, (string) $request->input('transaction_id', $orderId));
        } elseif (in_array($transactionStatus, ['expire'], true)) {
            $this->subscriptionService->markFailed($subscription, 'expired');
        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'failure'], true)) {
            $this->subscriptionService->markFailed($subscription, 'failed');
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Xendit invoice callback for platform subscription invoices.
     * URL: POST /api/webhooks/subscription/xendit
     */
    public function xendit(Request $request)
    {
        $expectedToken = (string) config('services.xendit.callback_token');
        $callbackToken = $request->header('X-CALLBACK-TOKEN');

        if ($expectedToken === '') {
            Log::warning('Subscription Xendit Webhook: callback token not configured');

            return response()->json(['status' => 'error', 'message' => 'Xendit callback token is not configured'], 400);
        }

        if (! is_string($callbackToken) || ! hash_equals($expectedToken, $callbackToken)) {
            Log::warning('Subscription Xendit Webhook: invalid callback token');

            return response()->json(['status' => 'error', 'message' => 'Invalid callback token'], 403);
        }

        $externalId = (string) $request->input('external_id');
        $status = (string) $request->input('status');

        $subscription = $this->subscriptionService->findByOrderId($externalId);

        if (! $subscription) {
            return response()->json(['status' => 'error', 'message' => 'Subscription not found'], 404);
        }

        if ($subscription->payment_status === 'paid') {
            return response()->json(['status' => 'success']);
        }

        if ($status === 'PAID' || $status === 'SETTLED') {
            $this->subscriptionService->activate($subscription, (string) $request->input('id', $externalId));
        } elseif ($status === 'EXPIRED') {
            $this->subscriptionService->markFailed($subscription, 'expired');
        }

        return response()->json(['status' => 'success']);
    }
}
