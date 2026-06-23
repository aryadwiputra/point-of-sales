<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    private function baseUrl(): ?string
    {
        $url = Setting::get('wa_service_url');
        return $url ?: null;
    }

    public function isAvailable(): bool
    {
        return $this->baseUrl() !== null
            && Setting::getBool('wa_enabled', false);
    }

    public function status(): array
    {
        try {
            $res = Http::timeout(5)->get($this->baseUrl().'/status');
            return $res->successful() ? $res->json() : ['connected' => false, 'error' => 'unreachable'];
        } catch (\Exception $e) {
            return ['connected' => false, 'error' => $e->getMessage()];
        }
    }

    public function start(): array
    {
        try {
            $res = Http::timeout(10)->post($this->baseUrl().'/start');
            return $res->successful() ? $res->json() : ['status' => false];
        } catch (\Exception $e) {
            return ['status' => false, 'error' => $e->getMessage()];
        }
    }

    public function send(string $target, string $message): bool
    {
        try {
            $res = Http::timeout(15)->post($this->baseUrl().'/send', [
                'target' => $target,
                'message' => $message,
            ]);
            return $res->successful() && ($res->json()['status'] ?? false);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function disconnect(): bool
    {
        try {
            $res = Http::timeout(10)->post($this->baseUrl().'/disconnect');
            return $res->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
