<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BihajuBinding;
use App\Services\Bihaju\BihajuClient;
use App\Support\AddonCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint callback Bihaju → BPOS.
 *
 * Bihaju mengirim POST ke {callback_base}/api/bihaju/webhook dengan:
 *   X-Event     : binding.approved | binding.revoked
 *   X-Signature : HMAC-SHA256(raw body, api_key partner)
 *
 * Path ini tetap (di belakang callback_url yang terdaftar di Bihaju).
 */
class BihajuWebhookController extends Controller
{
    public function __construct(
        private readonly BihajuClient $client,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = (string) $request->header('X-Signature', '');
        $event = (string) $request->header('X-Event', $request->input('event', ''));

        $partnerKey = $this->client->partnerKey();

        if (blank($partnerKey)) {
            Log::warning('Bihaju webhook ditolak: partner key belum dikonfigurasi.');

            return response()->json(['message' => 'Not configured.'], 503);
        }

        if (blank($signature)) {
            return response()->json(['message' => 'Signature wajib diisi.'], 401);
        }

        $expected = hash_hmac('sha256', $rawBody, (string) $partnerKey);

        if (! hash_equals($expected, $signature)) {
            Log::warning('Bihaju webhook signature tidak cocok.', [
                'event' => $event,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Signature tidak valid.'], 401);
        }

        $payload = $request->all();

        if (! is_array($payload) || blank($payload['external_ref'] ?? null)) {
            return response()->json(['message' => 'Payload tidak lengkap.'], 422);
        }

        $externalRef = (string) $payload['external_ref'];

        return match ($event) {
            'binding.approved' => $this->approved($payload, $externalRef),
            'binding.revoked' => $this->revoked($payload, $externalRef),
            default => response()->json([
                'message' => "Event '{$event}' tidak diproses.",
                'ignored' => true,
            ], 200),
        };
    }

    /**
     * Merchant menyetujui binding → simpan merchant_api_key (terenkripsi).
     *
     * @param  array<string, mixed>  $payload
     */
    private function approved(array $payload, string $externalRef): JsonResponse
    {
        $binding = BihajuBinding::withoutGlobalScopes()
            ->where('external_ref', $externalRef)
            ->first();

        if (! $binding) {
            // Binding tidak dikenal — jangan simpan sembarangan.
            Log::warning('Bihaju webhook binding.approved untuk external_ref tak dikenal.', [
                'external_ref' => $externalRef,
            ]);

            return response()->json(['message' => 'Binding tidak ditemukan.'], 404);
        }

        $merchant = is_array($payload['merchant'] ?? null) ? $payload['merchant'] : [];

        $binding->forceFill([
            'status' => BihajuBinding::STATUS_ACTIVE,
            'merchant_api_key' => $payload['merchant_api_key'] ?? $binding->merchant_api_key,
            'merchant_id' => $merchant['id'] ?? $binding->merchant_id,
            'merchant_name' => $merchant['name'] ?? $binding->merchant_name,
            'merchant_city' => $merchant['city'] ?? $binding->merchant_city,
            'merchant_status' => $merchant['status'] ?? $binding->merchant_status,
            'bound_at' => $binding->bound_at ?? now(),
            'revoked_at' => null,
            'last_callback' => $this->safeCallback($payload),
            'bind_code' => null,
            'bind_url' => null,
            'bind_code_expires_at' => null,
        ])->save();

        return response()->json([
            'message' => 'Binding disahkan.',
            'external_ref' => $binding->external_ref,
            'status' => $binding->status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function revoked(array $payload, string $externalRef): JsonResponse
    {
        $binding = BihajuBinding::withoutGlobalScopes()
            ->where('external_ref', $externalRef)
            ->first();

        if (! $binding) {
            return response()->json(['message' => 'Binding tidak ditemukan.'], 404);
        }

        $binding->forceFill([
            'status' => BihajuBinding::STATUS_REVOKED,
            // Merchant key dicabut: bersihkan agar tidak terpakai lagi.
            'merchant_api_key' => null,
            'revoked_at' => now(),
            'last_callback' => $this->safeCallback($payload),
        ])->save();

        return response()->json([
            'message' => 'Binding diputus.',
            'external_ref' => $binding->external_ref,
            'status' => $binding->status,
        ]);
    }

    /**
     * Simpan snapshot callback tanpa membawa kunci rahasia.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function safeCallback(array $payload): array
    {
        unset($payload['merchant_api_key']);

        return $payload;
    }
}
