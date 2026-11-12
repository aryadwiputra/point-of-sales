<?php

namespace App\Services\Bihaju;

use App\Models\BihajuBinding;
use App\Models\BihajuTransaction;
use App\Models\Outlet;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\AddonService;
use App\Services\Finance\FinanceEventEmitter;
use App\Services\TenantContext;
use App\Support\AddonCodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orkestrasi integrasi Bihaju di BPOS.
 *
 * Kebijakan: penjualan TIDAK PERNAH gagal karena Bihaju. Pengiriman bersifat
 * non-blocking — kegagalan dicatat sebagai pending/failed dan dicoba ulang
 * oleh perintah terjadwal `bihaju:dispatch-pending`.
 */
class BihajuService
{
    public function __construct(
        protected AddonService $addons,
        protected BihajuClient $client,
        protected FinanceEventEmitter $financeEvents,
    ) {}

    /**
     * Add-on Bihaju aktif dan partner key terkonfigurasi.
     */
    public function isConfigured(): bool
    {
        return $this->addons->isActive(AddonCodes::BIHAJU) && $this->client->isConfigured();
    }

    /**
     * Binding aktif untuk sebuah outlet (bila ada).
     */
    public function bindingForOutlet(?Outlet $outlet): ?BihajuBinding
    {
        if (! $outlet) {
            return null;
        }

        $binding = BihajuBinding::query()
            ->active()
            ->where('outlet_id', $outlet->id)
            ->first();

        return $binding?->isActive() ? $binding : null;
    }

    /**
     * Apakah transaksi ini layak dikirim ke Bihaju.
     *
     * Syarat: add-on aktif, outlet terikat, status pembayaran lunas,
     * dan bukan transaksi yang memerlukan approval diskon.
     */
    public function shouldSubmit(Transaction $transaction): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        if (! in_array($transaction->payment_status, ['paid', 'partial'], true)) {
            return false;
        }

        if ((int) $transaction->grand_total <= 0) {
            return false;
        }

        $transaction->loadMissing('warehouse.outlet');

        return $this->bindingForOutlet($transaction->warehouse?->outlet) !== null;
    }

    /**
     * Siapkan record pengiriman (idempoten per transaksi) lalu coba kirim.
     *
     * Dipanggil SETELAH commit transaksi checkout agar rollback tidak
     * meninggalkan record yatim.
     *
     * @return BihajuTransaction|null  null bila tidak layak dikirim
     */
    public function submitAfterCheckout(Transaction $transaction, ?string $memberCode = null): ?BihajuTransaction
    {
        if (! $this->shouldSubmit($transaction)) {
            return null;
        }

        $binding = $this->bindingForOutlet($transaction->warehouse?->outlet);

        if (! $binding) {
            return null;
        }

        $record = $this->record($transaction, $binding, $memberCode);

        $this->attempt($record);

        return $record;
    }

    /**
     * Buat (atau ambil) record pengiriman untuk transaksi.
     */
    public function record(Transaction $transaction, BihajuBinding $binding, ?string $memberCode = null): BihajuTransaction
    {
        $externalRef = $this->externalRefFor($transaction);

        $record = BihajuTransaction::withoutGlobalScopes()
            ->where('external_ref', $externalRef)
            ->first();

        if ($record) {
            return $record;
        }

        return BihajuTransaction::create([
            'tenant_id' => $transaction->tenant_id,
            'binding_id' => $binding->id,
            'transaction_id' => $transaction->id,
            'external_ref' => $externalRef,
            'amount' => (int) $transaction->grand_total,
            'member_code' => $memberCode ?: null,
            'status' => BihajuTransaction::STATUS_PENDING,
        ]);
    }

    /**
     * Idempotency key yang stabil untuk satu transaksi BPOS.
     */
    public function externalRefFor(Transaction $transaction): string
    {
        return sprintf('BPOS-%d-%d', (int) $transaction->tenant_id, $transaction->id);
    }

    /**
     * Coba kirim satu record. Tidak pernah melempar exception ke pemanggil.
     */
    public function attempt(BihajuTransaction $record): bool
    {
        if ($record->status === BihajuTransaction::STATUS_SUCCEEDED) {
            return true;
        }

        $binding = $record->binding;

        if (! $binding || ! $binding->isActive()) {
            $record->markFailed('Binding Bihaju tidak aktif.');

            return false;
        }

        $record->forceFill([
            'status' => BihajuTransaction::STATUS_SUBMITTED,
            'attempts' => $record->attempts + 1,
        ])->save();

        try {
            $payload = [
                'external_ref' => $record->external_ref,
                'amount' => (int) $record->amount,
                'occurred_at' => $record->transaction?->created_at?->toIso8601String()
                    ?? $record->created_at?->toIso8601String(),
            ];

            if (filled($record->member_code)) {
                $payload['member_code'] = $record->member_code;
            }

            $response = $this->client->createTransaction($binding, $payload);

            $record->markSucceeded($response);
            $record->loadMissing('transaction');

            // Bihaju→akuntansi: decoupled, non-blocking, idempotent per external_ref.
            DB::afterCommit(function () use ($record, $response): void {
                try {
                    $breakdown = is_array($record->breakdown) ? $record->breakdown : (is_array($response) ? $response : []);
                    $this->financeEvents->emitBihajuCommission($record->fresh() ?? $record, $breakdown);
                } catch (Throwable $exception) {
                    Log::info('Bihaju finance event emit skipped (non-blocking).', [
                        'external_ref' => $record->external_ref,
                        'error' => $exception->getMessage(),
                    ]);
                }
            });

            return true;
        } catch (BihajuApiException $exception) {
            if ($exception->isPermanent()) {
                // Gagal permanen (saldo kurang, member tidak ditemukan, dsb.)
                // — jangan dicoba ulang tanpa perubahan data.
                $record->markFailed($exception->getMessage());

                Log::warning('Pengiriman transaksi Bihaju ditolak.', [
                    'external_ref' => $record->external_ref,
                    'error' => $exception->getMessage(),
                ]);

                return false;
            }

            // Gagal sementara (5xx) — biarkan scheduler mencoba ulang.
            $record->forceFill([
                'status' => BihajuTransaction::STATUS_PENDING,
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();

            return false;
        } catch (Throwable $exception) {
            // Gagal sementara (jaringan/timeout) — biarkan scheduler mencoba ulang.
            $record->forceFill([
                'status' => BihajuTransaction::STATUS_PENDING,
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();

            Log::info('Pengiriman transaksi Bihaju tertunda.', [
                'external_ref' => $record->external_ref,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Coba ulang record yang masih tertunda (dipanggil scheduler).
     *
     * @return int jumlah record yang diproses
     */
    public function retryPending(int $limit = 50): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $maxAttempts = (int) config('bihaju.tries', 5);

        $records = BihajuTransaction::withoutGlobalScopes()
            ->where('status', BihajuTransaction::STATUS_PENDING)
            ->where('attempts', '<', $maxAttempts)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $processed = 0;

        foreach ($records as $record) {
            TenantContext::setById($record->tenant_id);

            if ($this->attempt($record)) {
                $processed++;
            }
        }

        TenantContext::reset();

        return $processed;
    }

    /**
     * Status ringkas untuk UI settings.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $tenant = TenantContext::get();

        $bindings = $tenant
            ? BihajuBinding::query()->with('outlet:id,name')->latest()->get()
            : collect();

        $transactions = $tenant
            ? BihajuTransaction::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->latest()
                ->limit(20)
                ->get()
            : collect();

        return [
            'configured' => $this->client->isConfigured(),
            'base_url' => $this->client->baseUrl(),
            'active' => $this->isConfigured(),
            'bindings' => $bindings->map(fn (BihajuBinding $b) => $b->toClientArray())->values(),
            'recent_transactions' => $transactions->map(fn (BihajuTransaction $t) => $t->toClientArray())->values(),
            'counts' => [
                'active' => $bindings->where('status', BihajuBinding::STATUS_ACTIVE)->count(),
                'pending' => $bindings->where('status', BihajuBinding::STATUS_PENDING)->count(),
                'succeeded' => $transactions->where('status', BihajuTransaction::STATUS_SUCCEEDED)->count(),
                'failed' => $transactions->where('status', BihajuTransaction::STATUS_FAILED)->count(),
                'pending_transactions' => $transactions->where('status', BihajuTransaction::STATUS_PENDING)->count(),
            ],
        ];
    }

    /**
     * Ambil tenant saat ini atau tenant eksplisit (untuk konteks queue/scheduler).
     */
    public function tenant(): ?Tenant
    {
        return TenantContext::get();
    }
}
