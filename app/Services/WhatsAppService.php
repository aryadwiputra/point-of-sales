<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Provider WhatsApp yang dipakai:
     * - "self_hosted" → service Node.js whatsapp-web.js (scan QR)
     * - "fonnte"      → Fonnte cloud API (cukup paste token)
     */
    public function provider(): string
    {
        $p = Setting::get('wa_provider', 'self_hosted');

        return in_array($p, ['self_hosted', 'fonnte'], true) ? $p : 'self_hosted';
    }

    private function baseUrl(): ?string
    {
        $url = Setting::get('wa_service_url');

        return $url ?: null;
    }

    private function fonnteToken(): ?string
    {
        $token = Setting::get('wa_fonnte_token');

        return $token ?: null;
    }

    public function isAvailable(): bool
    {
        if (! Setting::getBool('wa_enabled', false)) {
            return false;
        }

        return match ($this->provider()) {
            'fonnte'    => $this->fonnteToken() !== null,
            default     => $this->baseUrl() !== null,
        };
    }

    /**
     * Format nomor Indonesia: 08xxx → 628xxx
     */
    private function formatPhone(string $phone): string
    {
        $p = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($p, '0')) {
            return '62'.substr($p, 1);
        }
        if (str_starts_with($p, '8')) {
            return '62'.$p;
        }

        return $p;
    }

    public function status(): array
    {
        // Fonnte tidak punya status koneksi QR — selalu "siap" kalau token ada
        if ($this->provider() === 'fonnte') {
            return [
                'connected' => $this->fonnteToken() !== null,
                'phone'     => $this->fonnteToken() ? 'Fonnte Cloud API' : null,
                'qr'        => null,
                'starting'  => false,
                'provider'  => 'fonnte',
            ];
        }

        try {
            $res = Http::timeout(5)->get($this->baseUrl().'/status');

            return $res->successful() ? $res->json() : ['connected' => false, 'error' => 'unreachable'];
        } catch (\Exception $e) {
            return ['connected' => false, 'error' => $e->getMessage()];
        }
    }

    public function start(): array
    {
        // Fonnte tidak perlu start (cloud API)
        if ($this->provider() === 'fonnte') {
            return ['status' => true, 'message' => 'Fonnte cloud API — tidak perlu start'];
        }

        try {
            $res = Http::timeout(10)->post($this->baseUrl().'/start');

            return $res->successful() ? $res->json() : ['status' => false];
        } catch (\Exception $e) {
            return ['status' => false, 'error' => $e->getMessage()];
        }
    }

    public function send(string $target, string $message): bool
    {
        if ($this->provider() === 'fonnte') {
            return $this->sendViaFonnte($target, $message);
        }

        return $this->sendViaSelfHosted($target, $message);
    }

    private function sendViaSelfHosted(string $target, string $message): bool
    {
        try {
            $res = Http::timeout(15)->post($this->baseUrl().'/send', [
                'target'  => $target,
                'message' => $message,
            ]);

            return $res->successful() && ($res->json()['status'] ?? false);
        } catch (\Exception $e) {
            Log::error('[WhatsApp self_hosted] send failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Kirim via Fonnte Cloud API
     * Docs: https://docs.fonnte.com  •  Endpoint: https://api.fonnte.com/send
     * Auth: header `Authorization: <token>` (tanpa "Bearer")
     */
    private function sendViaFonnte(string $target, string $message): bool
    {
        $token = $this->fonnteToken();
        if (! $token) {
            Log::error('[Fonnte] token belum diset');

            return false;
        }

        try {
            $res = Http::timeout(20)
                ->withHeaders(['Authorization' => $token])
                ->post('https://api.fonnte.com/send', [
                    'target'       => $this->formatPhone($target),
                    'message'      => $message,
                    'countrycode'  => '62',
                ]);

            $data = $res->json();

            $ok = $data['status'] === true || $data['status'] === 'true';
            if (! $ok) {
                Log::error('[Fonnte] send failed', [
                    'target' => $target,
                    'reason' => $data['reason'] ?? null,
                    'raw'    => $data,
                ]);
            }

            return $ok;
        } catch (\Exception $e) {
            Log::error('[Fonnte] exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function disconnect(): bool
    {
        // Fonnte tidak punya disconnect (cloud API)
        if ($this->provider() === 'fonnte') {
            return true;
        }

        try {
            $res = Http::timeout(10)->post($this->baseUrl().'/disconnect');

            return $res->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
