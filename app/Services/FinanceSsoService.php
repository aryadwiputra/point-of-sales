<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Support\AddonCodes;
use App\Support\HmacJwt;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * SSO BPOS → aplikasi Finance (akuntansi terpisah).
 *
 * BPOS menerbitkan token HS256 berisi identitas, tenant, dan entitlement
 * add-on. Aplikasi Finance memverifikasi signature + expiry pada SETIAP
 * request, sehingga gating tidak hanya mengandalkan UI BPOS.
 */
class FinanceSsoService
{
    public function secret(): string
    {
        $secret = config('finance.sso_secret');

        if (blank($secret)) {
            $secret = config('app.key');
        }

        if (blank($secret)) {
            throw new RuntimeException('FINANCE_SSO_SECRET belum dikonfigurasi.');
        }

        return (string) $secret;
    }

    public function bridgeSecret(): string
    {
        $secret = config('finance.bridge_secret');

        return (string) ($secret ?: $this->secret());
    }

    /**
     * Daftar add-on/fitur yang dimiliki tenant saat ini.
     *
     * @return array<int, string>
     */
    public function entitlements(?Tenant $tenant): array
    {
        if (! $tenant) {
            return AddonCodes::ALL;
        }

        $features = array_values(array_unique(array_merge(
            $tenant->plan?->features ?? [],
            $tenant->activeAddons()->pluck('code')->all(),
        )));

        return $features;
    }

    /**
     * @return array<string, mixed>
     */
    public function claims(?Tenant $tenant, ?User $user, int $ttl): array
    {
        $now = time();

        return [
            'iss' => config('app.url'),
            'aud' => 'bpos-finance',
            'sub' => (string) ($user?->id ?? ''),
            'tenant_id' => $tenant?->id,
            'tenant_name' => $tenant?->name,
            'name' => $user?->name,
            'email' => $user?->email,
            'role' => $user?->getRoleNames()->first(),
            'scopes' => ['finance:read', 'finance:write'],
            'entitlements' => $this->entitlements($tenant),
            'addons' => $this->entitlements($tenant),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
            'jti' => (string) Str::uuid(),
        ];
    }

    public function issue(?Tenant $tenant, ?User $user, ?int $ttl = null): string
    {
        $ttl = $ttl ?? (int) config('finance.sso_ttl', 300);

        return HmacJwt::encode($this->claims($tenant, $user, $ttl), $this->secret());
    }

    /**
     * Verifikasi token SSO; melempar RuntimeException bila tidak sah.
     *
     * @return array<string, mixed>
     */
    public function verify(string $token): array
    {
        $claims = HmacJwt::decode($token, $this->secret());

        $entitlements = $claims['entitlements'] ?? $claims['addons'] ?? [];

        if (! in_array(AddonCodes::FINANCE, $entitlements, true)) {
            throw new RuntimeException('Token tidak memiliki entitlement finance.');
        }

        return $claims;
    }

    /**
     * URL peluncuran aplikasi Finance untuk user/tenant tertentu.
     */
    public function launchUrl(?Tenant $tenant, ?User $user, ?int $ttl = null): string
    {
        $token = $this->issue($tenant, $user, $ttl);

        $base = rtrim((string) config('finance.url'), '/');

        return $base.'/sso?token='.urlencode($token);
    }

    /**
     * Header otorisasi untuk panggilan keluar BPOS → aplikasi Finance.
     *
     * @return array<string, string>
     */
    public function bridgeHeaders(?Tenant $tenant): array
    {
        $token = $this->issue($tenant, null, 300);

        return [
            'Authorization' => 'Bearer '.$token,
            'X-Finance-Signature' => hash_hmac('sha256', (string) $tenant?->id, $this->bridgeSecret()),
            'Accept' => 'application/json',
        ];
    }
}
