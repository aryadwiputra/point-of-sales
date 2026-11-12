<?php

namespace App\Services\Finance;

use App\Models\ReceivablePayment;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\AddonService;
use App\Services\FinanceSsoService;
use App\Services\TenantContext;
use App\Support\AddonCodes;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pengirim event BPOS → aplikasi Finance (add-on terpisah).
 *
 * Non-blocking: kegagalan jaringan/konfigurasi hanya dicatat (log) dan TIDAK
 * PERNAH menggagalkan penjualan/pembayaran. Panggilan harus dijadwalkan
 * setelah commit (DB::afterCommit) agar tidak mengirim data yang di-rollback.
 * Finance menjamin idempotensi per idempotency_key.
 */
class FinanceEventEmitter
{
    public function __construct(
        private readonly AddonService $addons,
        private readonly FinanceSsoService $sso,
    ) {}

    public function emitSaleCompleted(Transaction $transaction): void
    {
        if (! $this->shouldEmit($transaction->tenant_id)) {
            return;
        }

        $tenantId = (int) $transaction->tenant_id;
        $amount = (float) $transaction->grand_total;

        if ($amount <= 0) {
            return;
        }

        $this->emit([
            'event' => 'sale.completed',
            'tenant_id' => $tenantId,
            'tenant_name' => $this->tenantName($transaction),
            'idempotency_key' => sprintf('bpos:sale.completed:%d:%d', $tenantId, $transaction->id),
            'occurred_at' => optional($transaction->created_at)->toIso8601String() ?? now()->toIso8601String(),
            'source_document_type' => 'transaction',
            'source_document_id' => $transaction->id,
            'amount' => $amount,
            'components' => [
                'subtotal' => (float) (($transaction->grand_total ?? 0) - ($transaction->tax_total ?? 0) - ($transaction->shipping_cost ?? 0) + ($transaction->discount ?? 0)),
                'discount' => (float) ($transaction->discount ?? 0),
                'tax' => (float) ($transaction->tax_total ?? 0),
                'shipping' => (float) ($transaction->shipping_cost ?? 0),
            ],
            'description' => 'Penjualan POS '.$transaction->invoice,
            'account_hints' => [
                'cash_account' => 'cash_account',
            ],
        ], $tenantId);
    }

    public function emitReceivablePayment(ReceivablePayment $payment): void
    {
        $payment->loadMissing('receivable.transaction');

        $receivable = $payment->receivable;
        $transaction = $receivable?->transaction;
        $tenantId = (int) ($transaction?->tenant_id ?? $receivable?->tenant_id ?? 0);

        if (! $this->shouldEmit($tenantId ?: null)) {
            return;
        }

        $amount = (float) $payment->amount;
        if ($amount <= 0) {
            return;
        }

        $invoice = $receivable?->invoice ?? $transaction?->invoice;

        $this->emit([
            'event' => 'payment.received',
            'tenant_id' => $tenantId,
            'tenant_name' => $transaction?->exists ? $this->tenantName($transaction) : $this->tenantNameById($tenantId),
            'idempotency_key' => sprintf('bpos:receivable_payment:%d:%d', $tenantId, $payment->id),
            'occurred_at' => optional($payment->paid_at)->startOfDay()->toIso8601String()
                ?? optional($payment->created_at)->toIso8601String()
                ?? now()->toIso8601String(),
            'source_document_type' => 'receivable_payment',
            'source_document_id' => $payment->id,
            'amount' => $amount,
            'description' => 'Pembayaran piutang'.($invoice ? " {$invoice}" : ''),
            'account_hints' => [
                'cash_account' => 'cash_account',
            ],
        ], $tenantId);
    }

    public function emitPaymentReceivedForTransaction(Transaction $transaction, string $context = 'confirmed'): void
    {
        if (! $this->shouldEmit($transaction->tenant_id)) {
            return;
        }

        $tenantId = (int) $transaction->tenant_id;
        $amount = (float) $transaction->grand_total;

        if ($amount <= 0) {
            return;
        }

        $ref = $transaction->payment_reference ?: $context;

        $this->emit([
            'event' => 'payment.received',
            'tenant_id' => $tenantId,
            'tenant_name' => $this->tenantName($transaction),
            'idempotency_key' => sprintf('bpos:payment.received:%d:%d:%s', $tenantId, $transaction->id, $ref),
            'occurred_at' => now()->toIso8601String(),
            'source_document_type' => 'transaction',
            'source_document_id' => $transaction->id,
            'amount' => $amount,
            'description' => 'Penerimaan pembayaran '.$transaction->invoice,
            'account_hints' => [
                'cash_account' => 'cash_account',
            ],
        ], $tenantId);
    }

    /**
     * Emit komisi/settlement Bihaju (decoupled, dipanggil setelah Bihaju sukses).
     *
     * @param  array<string, mixed>|null  $breakdown
     */
    public function emitBihajuCommission(\App\Models\BihajuTransaction $record, ?array $breakdown = null): void
    {
        $tenantId = (int) $record->tenant_id;

        if (! $this->shouldEmit($tenantId ?: null)) {
            return;
        }

        $breakdown = $breakdown ?? (is_array($record->breakdown) ? $record->breakdown : []);

        $commission = (float) ($breakdown['merchant_commission'] ?? 0);
        $fee = (float) ($breakdown['platform_fee'] ?? 0);

        if ($commission <= 0 && $fee <= 0) {
            return;
        }

        $transaction = $record->relationLoaded('transaction') ? $record->transaction : $record->transaction()->withoutGlobalScopes()->first();
        $tenantName = $transaction?->exists ? $this->tenantName($transaction) : $this->tenantNameById($tenantId);

        $this->emit([
            'event' => 'bihaju.commission',
            'tenant_id' => $tenantId,
            'tenant_name' => $tenantName,
            'idempotency_key' => sprintf('bpos:bihaju.commission:%d:%s', $tenantId, $record->external_ref ?: $record->id),
            'occurred_at' => optional($record->submitted_at)->toIso8601String() ?? now()->toIso8601String(),
            'source_document_type' => 'bihaju_transaction',
            'source_document_id' => $record->id,
            'amount' => $commission,
            'external_ref' => $record->external_ref,
            'breakdown' => [
                'merchant_commission' => $commission,
                'platform_fee' => $fee,
                'member_cashback' => (float) ($breakdown['member_cashback'] ?? 0),
            ],
            'description' => 'Komisi Bihaju '.$record->external_ref,
        ], $tenantId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emit(array $payload, ?int $tenantId): void
    {
        $url = rtrim((string) config('finance.url'), '/');

        if ($url === '') {
            return;
        }

        $tenant = $this->resolveTenant($tenantId);

        try {
            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($body === false) {
                return;
            }

            $signature = hash_hmac('sha256', $body, $this->sso->bridgeSecret());
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Finance-Signature' => $signature,
            ];

            try {
                $headers['Authorization'] = 'Bearer '.$this->sso->issue($tenant, null, 300);
            } catch (Throwable) {
                // Token identitas opsional; signature bridge yang utama.
            }

            Http::timeout((int) config('finance.timeout', 10))
                ->withHeaders($headers)
                ->withBody($body, 'application/json')
                ->post($url.'/api/v1/events');
        } catch (Throwable $exception) {
            Log::warning('Finance event emit failed (non-blocking).', [
                'event' => $payload['event'] ?? null,
                'tenant_id' => $payload['tenant_id'] ?? null,
                'idempotency_key' => $payload['idempotency_key'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function shouldEmit(?int $tenantId): bool
    {
        if (blank(config('finance.url'))) {
            return false;
        }

        $secret = config('finance.bridge_secret', config('finance.sso_secret', config('app.key')));
        if (blank($secret)) {
            return false;
        }

        $tenant = $this->resolveTenant($tenantId);
        $previous = TenantContext::get();

        try {
            if ($tenant) {
                TenantContext::set($tenant);
            }

            return $this->addons->isActive(AddonCodes::FINANCE);
        } catch (Throwable) {
            return false;
        } finally {
            if ($tenant) {
                if ($previous) {
                    TenantContext::set($previous);
                } else {
                    TenantContext::reset();
                }
            }
        }
    }

    private function resolveTenant(?int $tenantId): ?Tenant
    {
        if ($tenantId) {
            return Tenant::withoutGlobalScopes()->find($tenantId);
        }

        return TenantContext::get();
    }

    private function tenantName(?Transaction $transaction): ?string
    {
        if (! $transaction) {
            return null;
        }

        return $this->tenantNameById($transaction->tenant_id) ?? TenantContext::get()?->name;
    }

    private function tenantNameById(?int $tenantId): ?string
    {
        if (! $tenantId) {
            return TenantContext::get()?->name;
        }

        return Tenant::withoutGlobalScopes()->find($tenantId)?->name;
    }
}
