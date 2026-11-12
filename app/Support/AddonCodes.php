<?php

namespace App\Support;

/**
 * Kode entitas add-on berbayar yang diaktifkan per tenant.
 *
 * Dipakai sebagai:
 *  - nilai kolom `tenant_addons.code`
 *  - nama fitur pada `plans.features` (gating paket)
 *  - parameter middleware `feature:{code}` / `addon:{code}`
 */
final class AddonCodes
{
    /** Aplikasi akuntansi terpisah (Finance) — add-on untuk tenant besar. */
    public const FINANCE = 'finance';

    /** Integrasi platform Bihaju (binding merchant + transaksi komisi/cashback). */
    public const BIHAJU = 'bihaju';

    /** @var array<int, string> */
    public const ALL = [self::FINANCE, self::BIHAJU];

    public static function isValid(string $code): bool
    {
        return in_array($code, self::ALL, true);
    }

    public static function label(string $code): string
    {
        return match ($code) {
            self::FINANCE => 'Finance (Akuntansi)',
            self::BIHAJU => 'Integrasi Bihaju',
            default => $code,
        };
    }

    public static function description(string $code): string
    {
        return match ($code) {
            self::FINANCE => 'Aplikasi akuntansi terpisah: COA, jurnal, periode fiskal, dan laporan keuangan.',
            self::BIHAJU => 'Hubungkan outlet ke ekosistem Bihaju: binding merchant dan transaksi komisi/cashback.',
            default => '',
        };
    }
}
