<?php

namespace App\Services\Bihaju;

use App\Models\BihajuBinding;
use App\Services\AddonService;
use App\Support\AddonCodes;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien HTTP Partner API Bihaju.
 *
 * Autentikasi:
 *  - X-Partner-Key  → pk_live_... (berlaku platform / dapat dioverride per tenant)
 *  - X-Merchant-Key → mch_live_... (diterima saat binding, disimpan terenkripsi)
 *
 * Referensi: Bihaju core → docs/api-partner.md
 */
class BihajuClient
{
    public function __construct(protected AddonService $addons)
    {
    }

    public function isConfigured(): bool
    {
        return filled($this->partnerKey());
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('bihaju.base_url'), '/');
    }

    /**
     * Partner key: override per tenant (tenant_addons.secret) → env platform.
     */
    public function partnerKey(): ?string
    {
        $override = $this->addons->find(AddonCodes::BIHAJU)?->secret;

        return filled($override) ? (string) $override : config('bihaju.partner_key');
    }

    protected function request(): PendingRequest
    {
        $partnerKey = $this->partnerKey();

        if (blank($partnerKey)) {
            throw new RuntimeException(
                'Partner key Bihaju belum dikonfigurasi (BIHAJU_PARTNER_KEY).'
            );
        }

        return Http::timeout((int) config('bihaju.timeout', 10))
            ->acceptJson()
            ->withHeaders([
                'X-Partner-Key' => (string) $partnerKey,
            ]);
    }

    protected function merchantRequest(BihajuBinding|string $bindingOrKey): PendingRequest
    {
        $merchantKey = $bindingOrKey instanceof BihajuBinding
            ? $bindingOrKey->merchant_api_key
            : $bindingOrKey;

        if (blank($merchantKey)) {
            throw new RuntimeException('Merchant key Bihaju belum tersedia (binding belum disahkan).');
        }

        return $this->request()->withHeaders([
            'X-Merchant-Key' => (string) $merchantKey,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function decode(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Ambil pesan error yang berguna dari respons Bihaju.
     */
    protected function errorMessage(Response $response): string
    {
        $json = $response->json();

        if (is_array($json)) {
            $message = $json['message'] ?? null;
            $code = $json['code'] ?? null;

            if ($message && $code) {
                return "{$message} ({$code})";
            }

            if ($message) {
                return (string) $message;
            }
        }

        return 'HTTP '.$response->status();
    }

    // ------------------------------------------------------------------
    // Binding
    // ------------------------------------------------------------------

    /**
     * POST /api/partner/bindings/qr
     *
     * @return array{code: string, url: string, external_ref: string, expires_at: ?string}
     */
    public function createBindingQr(string $externalRef): array
    {
        $response = $this->request()
            ->post($this->baseUrl().'/api/partner/bindings/qr', [
                'external_ref' => $externalRef,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gagal membuat QR binding: '.$this->errorMessage($response));
        }

        $json = $this->decode($response);

        if (blank($json['url'] ?? null)) {
            throw new RuntimeException('Respons QR binding tidak lengkap dari Bihaju.');
        }

        return [
            'code' => (string) ($json['code'] ?? ''),
            'url' => (string) $json['url'],
            'external_ref' => (string) ($json['external_ref'] ?? $externalRef),
            'expires_at' => $json['expires_at'] ?? null,
        ];
    }

    /**
     * GET /api/partner/bindings — riwayat binding (untuk resync).
     *
     * @return array<int, array<string, mixed>>
     */
    public function bindings(): array
    {
        $response = $this->request()->get($this->baseUrl().'/api/partner/bindings');

        if ($response->failed()) {
            throw new RuntimeException('Gagal mengambil daftar binding: '.$this->errorMessage($response));
        }

        return $this->decode($response)['data'] ?? [];
    }

    /**
     * GET /api/partner/merchants — merchant yang sedang terikat.
     *
     * @return array<int, array<string, mixed>>
     */
    public function merchants(): array
    {
        $response = $this->request()->get($this->baseUrl().'/api/partner/merchants');

        if ($response->failed()) {
            throw new RuntimeException('Gagal mengambil daftar merchant: '.$this->errorMessage($response));
        }

        return $this->decode($response)['data'] ?? [];
    }

    // ------------------------------------------------------------------
    // Merchant-scoped
    // ------------------------------------------------------------------

    /**
     * GET /api/partner/me — profil & saldo merchant.
     *
     * @return array<string, mixed>
     */
    public function merchantProfile(BihajuBinding $binding): array
    {
        $response = $this->merchantRequest($binding)->get($this->baseUrl().'/api/partner/me');

        if ($response->failed()) {
            throw new RuntimeException('Gagal mengambil profil merchant: '.$this->errorMessage($response));
        }

        return $this->decode($response);
    }

    /**
     * GET /api/partner/members?q=... — pencarian member (min. 3 karakter).
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchMembers(BihajuBinding $binding, string $query): array
    {
        if (mb_strlen(trim($query)) < 3) {
            throw new RuntimeException('Kata kunci member minimal 3 karakter.');
        }

        $response = $this->merchantRequest($binding)
            ->get($this->baseUrl().'/api/partner/members', ['q' => $query]);

        if ($response->failed()) {
            throw new RuntimeException('Gagal mencari member: '.$this->errorMessage($response));
        }

        return $this->decode($response)['data'] ?? [];
    }

    /**
     * POST /api/partner/transactions — idempoten per external_ref.
     *
     * @param  array{external_ref: string, amount: int, member_code?: ?string, member_id?: ?int, occurred_at?: ?string}  $payload
     * @return array<string, mixed>
     */
    public function createTransaction(BihajuBinding $binding, array $payload): array
    {
        $response = $this->merchantRequest($binding)
            ->post($this->baseUrl().'/api/partner/transactions', $payload);

        // 409/422 dari Bihaju (mis. saldo kurang, member tidak ditemukan)
        // dibuang sebagai exception agar job dapat mencatat status gagal.
        if ($response->failed()) {
            throw new BihajuApiException($this->errorMessage($response), $response->status());
        }

        return $this->decode($response);
    }

    /**
     * GET /api/partner/transactions — riwayat transaksi merchant.
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function transactions(BihajuBinding $binding, array $filters = []): array
    {
        $response = $this->merchantRequest($binding)
            ->get($this->baseUrl().'/api/partner/transactions', $filters);

        if ($response->failed()) {
            throw new RuntimeException('Gagal mengambil riwayat transaksi: '.$this->errorMessage($response));
        }

        $json = $this->decode($response);

        return [
            'data' => $json['data'] ?? [],
            'meta' => $json['meta'] ?? [],
        ];
    }
}
