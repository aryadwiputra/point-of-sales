<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Support\AddonCodes;
use Illuminate\Support\Facades\DB;

/**
 * Entitlement add-on berbayar per tenant.
 *
 * Dua sumber ketersediaan (keduanya dihormati):
 *  1. Paket langganan menyertakan fitur (`plans.features` berisi kode add-on).
 *  2. Baris `tenant_addons` aktif (add-on dibeli/diaktifkan terpisah dari paket).
 *
 * Sebuah add-on dianggap AKTIF bila tenant memenuhi salah satu sumber di atas,
 * tidak disuspend, dan belum kedaluwarsa. Selalu periksa dua sisi:
 * BPOS memakai service ini untuk gating UI/route, aplikasi akuntansi memvalidasi
 * klaim/token yang sama pada setiap request.
 */
class AddonService
{
    public function __construct(protected QuotaService $quota)
    {
    }

    public function getTenant(): ?Tenant
    {
        return $this->quota->getTenant();
    }

    /**
     * Apakah tenant berhak atas add-on (baik dari paket maupun pembelian terpisah).
     * Tidak memeriksa status aktif — lihat isActive().
     */
    public function isEntitled(string $code): bool
    {
        if (! AddonCodes::isValid($code)) {
            return false;
        }

        if (TenantContext::isBypassed()) {
            return true;
        }

        $tenant = $this->getTenant();
        if (! $tenant) {
            return true;
        }

        // Paket langganan menyertakan add-on.
        if ($tenant->plan && $tenant->plan->hasFeature($code)) {
            return true;
        }

        // Add-on dibeli/diaktifkan terpisah dari paket.
        return TenantAddon::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', $code)
            ->exists();
    }

    /**
     * Apakah sebuah fitur/add-on benar-benar dapat dipakai sekarang.
     *
     * Untuk kode di luar daftar add-on (mis. 'crm') perilakunya sama dengan
     * QuotaService::hasFeature: cukup paket langganan yang menyertakannya.
     *
     * Untuk kode add-on ('finance', 'bihaju'): paket yang menyertakannya ATAU
     * baris tenant_addons aktif — dan tidak boleh disuspend/kedaluwarsa.
     */
    public function isActive(string $code): bool
    {
        if (TenantContext::isBypassed()) {
            return true;
        }

        $tenant = $this->getTenant();

        // Local dev / superadmin tanpa tenant: layanan tetap terbuka.
        if (! $tenant) {
            return true;
        }

        // Fitur paket biasa — pertahankan perilaku lama (tanpa plan = terbuka).
        if (! AddonCodes::isValid($code)) {
            return $tenant->plan ? $tenant->plan->hasFeature($code) : true;
        }

        $addon = TenantAddon::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', $code)
            ->first();

        if ($addon) {
            if ($addon->status === TenantAddon::STATUS_SUSPENDED) {
                return false;
            }

            if ($addon->status === TenantAddon::STATUS_ACTIVE) {
                return $addon->isActive() && $tenant->isActive();
            }

            // status 'inactive' → jatuh ke pengecekan paket di bawah
        }

        // Add-on dibeli lewat paket (mis. Enterprise menyertakan 'finance').
        return $tenant->plan && $tenant->plan->hasFeature($code) && $tenant->isActive();
    }

    public function activate(string $code, array $config = [], ?\DateTimeInterface $expiresAt = null): TenantAddon
    {
        abort_unless(AddonCodes::isValid($code), 422, "Add-on '{$code}' tidak dikenal.");

        $tenant = $this->getTenant();
        abort_unless($tenant, 422, 'Konteks tenant tidak ditemukan.');

        return DB::transaction(function () use ($tenant, $code, $config, $expiresAt) {
            $addon = TenantAddon::query()
                ->where('tenant_id', $tenant->id)
                ->where('code', $code)
                ->lockForUpdate()
                ->first();

            if (! $addon) {
                $addon = new TenantAddon([
                    'tenant_id' => $tenant->id,
                    'code' => $code,
                ]);
            }

            $addon->status = TenantAddon::STATUS_ACTIVE;
            $addon->config = array_merge($addon->config ?? [], $config);
            $addon->activated_at = $addon->activated_at ?? now();
            $addon->expires_at = $expiresAt;
            $addon->save();

            return $addon;
        });
    }

    public function deactivate(string $code): void
    {
        $tenant = $this->getTenant();
        if (! $tenant) {
            return;
        }

        TenantAddon::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', $code)
            ->update(['status' => TenantAddon::STATUS_INACTIVE]);
    }

    public function find(string $code): ?TenantAddon
    {
        $tenant = $this->getTenant();
        if (! $tenant || ! AddonCodes::isValid($code)) {
            return null;
        }

        return TenantAddon::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', $code)
            ->first();
    }

    public function config(string $code, string $key, mixed $default = null): mixed
    {
        return $this->find($code)?->config($key, $default) ?? $default;
    }

    /**
     * Daftar add-on beserta statusnya untuk ditampilkan di UI settings.
     *
     * @return array<int, array<string, mixed>>
     */
    public function status(): array
    {
        $tenant = $this->getTenant();

        $addons = $tenant
            ? TenantAddon::query()->where('tenant_id', $tenant->id)->get()->keyBy('code')
            : collect();

        return array_map(fn (string $code) => [
            'code' => $code,
            'label' => AddonCodes::label($code),
            'description' => AddonCodes::description($code),
            'entitled' => $this->isEntitled($code),
            'active' => $this->isActive($code),
            'config' => $addons->get($code)?->config ?? [],
            'activated_at' => $addons->get($code)?->activated_at,
            'expires_at' => $addons->get($code)?->expires_at,
        ], AddonCodes::ALL);
    }
}
