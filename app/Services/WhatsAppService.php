<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    private function baseUrl(?Outlet $outlet = null): ?string
    {
        $url = Setting::getForOutlet('wa_service_url', $outlet);

        return $url ?: null;
    }

    /**
     * Shared secret for the Node WhatsApp service (X-Service-Token header).
     */
    private function serviceToken(): ?string
    {
        $token = config('services.whatsapp.service_token') ?: env('WA_SERVICE_TOKEN');

        return filled($token) ? (string) $token : null;
    }

    private function request(?Outlet $outlet = null)
    {
        $request = Http::timeout(15);

        if ($token = $this->serviceToken()) {
            $request = $request->withHeaders(['X-Service-Token' => $token]);
        }

        return $request;
    }

    public function isAvailable(?Outlet $outlet = null): bool
    {
        return $this->baseUrl($outlet) !== null
            && Setting::getBoolForOutlet('wa_enabled', $outlet, false);
    }

    public function status(?Outlet $outlet = null): array
    {
        try {
            $res = $this->request($outlet)->timeout(5)->get($this->baseUrl($outlet).'/status');

            return $res->successful() ? $res->json() : ['connected' => false, 'error' => 'unreachable'];
        } catch (\Exception $e) {
            return ['connected' => false, 'error' => $e->getMessage()];
        }
    }

    public function start(?Outlet $outlet = null): array
    {
        try {
            $res = $this->request($outlet)->timeout(10)->post($this->baseUrl($outlet).'/start');

            return $res->successful() ? $res->json() : ['status' => false];
        } catch (\Exception $e) {
            return ['status' => false, 'error' => $e->getMessage()];
        }
    }

    public function send(string $target, string $message, ?Outlet $outlet = null): bool
    {
        try {
            $res = $this->request($outlet)->post($this->baseUrl($outlet).'/send', [
                'target' => $target,
                'message' => $message,
            ]);

            return $res->successful() && ($res->json()['status'] ?? false);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function disconnect(?Outlet $outlet = null): bool
    {
        try {
            $res = $this->request($outlet)->timeout(10)->post($this->baseUrl($outlet).'/disconnect');

            return $res->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
