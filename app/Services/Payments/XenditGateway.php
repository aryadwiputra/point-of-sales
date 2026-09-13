<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentGatewayException;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;

class XenditGateway
{
    public function createInvoice(Transaction $transaction, array $config, ?int $amount = null, ?string $externalId = null): array
    {
        return $this->createInvoiceRequest($transaction, $config, null, $amount, $externalId);
    }

    /**
     * Dynamic QRIS invoice — QRIS-only channel; returns qr_string.
     */
    public function createQrisInvoice(Transaction $transaction, array $config, ?int $amount = null, ?string $externalId = null): array
    {
        $result = $this->createInvoiceRequest($transaction, $config, ['QRIS'], $amount, $externalId);

        return [
            ...$result,
            'qr_string' => $result['raw']['qr_string'] ?? null,
        ];
    }

    private function createInvoiceRequest(Transaction $transaction, array $config, ?array $channels = null, ?int $amount = null, ?string $externalId = null): array
    {
        if (! ($config['enabled'] ?? false)) {
            throw new PaymentGatewayException('Xendit tidak aktif atau belum dikonfigurasi.');
        }

        $customer = $transaction->customer;

        $payload = [
            'external_id' => $externalId ?? $transaction->invoice,
            'amount' => $amount ?? (int) $transaction->grand_total,
            'description' => 'Pembayaran transaksi #'.$transaction->invoice,
            'customer' => [
                'given_names' => optional($customer)->name ?? 'Customer',
                'email' => optional($customer)->email ?? config('mail.from.address'),
                'mobile_number' => optional($customer)->no_telp,
            ],
            'success_redirect_url' => route('transactions.print', $transaction->invoice),
        ];

        if ($channels !== null) {
            $payload['channel_code'] = $channels;
        }

        $response = Http::withBasicAuth($config['secret_key'], '')
            ->post('https://api.xendit.co/v2/invoices', $payload);

        if ($response->failed()) {
            throw new PaymentGatewayException(
                'Xendit error: '.$response->json('message', $response->body())
            );
        }

        return [
            'reference' => $response->json('id'),
            'payment_url' => $response->json('invoice_url'),
            'raw' => $response->json(),
        ];
    }
}
