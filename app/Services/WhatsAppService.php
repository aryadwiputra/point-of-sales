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

    public function isAvailable(?Outlet $outlet = null): bool
    {
        return $this->baseUrl($outlet) !== null
            && Setting::getBoolForOutlet('wa_enabled', $outlet, false);
    }

    public function status(?Outlet $outlet = null): array
    {
        try {
            $res = Http::timeout(5)->get($this->baseUrl($outlet).'/status');

            return $res->successful() ? $res->json() : ['connected' => false, 'error' => 'unreachable'];
        } catch (\Exception $e) {
            return ['connected' => false, 'error' => $e->getMessage()];
        }
    }

    public function start(?Outlet $outlet = null): array
    {
        try {
            $res = Http::timeout(10)->post($this->baseUrl($outlet).'/start');

            return $res->successful() ? $res->json() : ['status' => false];
        } catch (\Exception $e) {
            return ['status' => false, 'error' => $e->getMessage()];
        }
    }

    public function send(string $target, string $message, ?Outlet $outlet = null): bool
    {
        try {
            $res = Http::timeout(15)->post($this->baseUrl($outlet).'/send', [
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
            $res = Http::timeout(10)->post($this->baseUrl($outlet).'/disconnect');

            return $res->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
