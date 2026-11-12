<?php

namespace App\Services;

use App\Models\Plan;

/**
 * Biaya transaksi untuk paket model "bayar per transaksi"
 * (billing_type = per_transaction, tanpa langganan bulanan/tahunan).
 *
 * Formula (semua nilai diset Superadmin pada menu Paket Langganan):
 *   dasar = persentase (%) dari grand_total  — atau —  nominal tetap (Rp)
 *   lalu dijepit ke rentang [fee_min, fee_max]
 *   lalu dibulatkan sesuai fee_rounding
 *
 * Perhitungan SELALU dilakukan di server. Frontend hanya menampilkan
 * preview memakai config() dengan formula yang sama; nilai final pada
 * transaksi yang tersimpan dihitung ulang oleh service ini saat checkout.
 *
 * Biaya ini adalah biaya pemakaian platform (bukan tagihan ke pelanggan),
 * sehingga tidak ditambahkan ke grand_total.
 */
class TransactionFeeService
{
    public const BILLING_SUBSCRIPTION = 'subscription';

    public const BILLING_PER_TRANSACTION = 'per_transaction';

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    public const ROUND_NONE = 'none';

    public const ROUND_UP = 'up';

    public const ROUND_DOWN = 'down';

    /**
     * Konfigurasi biaya untuk frontend (preview di layar POS).
     * Null bila tenant tidak memakai paket pay-per-transaksi.
     */
    public function config(): ?array
    {
        $plan = $this->plan();

        if (! $plan || $plan->billing_type !== self::BILLING_PER_TRANSACTION) {
            return null;
        }

        return [
            'billing_type' => $plan->billing_type,
            'fee_type' => $plan->fee_type ?: self::TYPE_PERCENT,
            'fee_value' => (float) $plan->fee_value,
            'fee_min' => (int) $plan->fee_min,
            'fee_max' => $plan->fee_max !== null ? (int) $plan->fee_max : null,
            'fee_rounding' => $plan->fee_rounding ?: self::ROUND_NONE,
            'fee_methods' => $plan->fee_methods,
        ];
    }

    /** Apakah paket aktif tenant kena biaya per transaksi. */
    public function applies(): bool
    {
        return $this->config() !== null;
    }

    /**
     * Hitung biaya transaksi (dalam Rupiah, bulat).
     *
     * @param  int  $amount  grand_total transaksi
     * @param  array<int, string>  $methods  metode bayar yang terlibat
     *                                       (split → banyak metode; pay_later → ['pay_later'])
     * @return int 0 bila paket bukan per-transaksi atau metode tidak kena biaya
     */
    public function calculate(int $amount, array $methods = []): int
    {
        $config = $this->config();

        if (! $config || $amount <= 0) {
            return 0;
        }

        // Filter metode bayar: kosong = semua metode kena biaya.
        $allowed = $config['fee_methods'];
        if (! empty($allowed) && array_intersect($methods, $allowed) === []) {
            return 0;
        }

        $fee = $config['fee_type'] === self::TYPE_FIXED
            ? (float) $config['fee_value']
            : ($amount * (float) $config['fee_value']) / 100;

        if ($config['fee_min'] > 0 && $fee < $config['fee_min']) {
            $fee = (float) $config['fee_min'];
        }

        if ($config['fee_max'] !== null && $config['fee_max'] > 0 && $fee > $config['fee_max']) {
            $fee = (float) $config['fee_max'];
        }

        return match ($config['fee_rounding']) {
            self::ROUND_UP => (int) ceil($fee),
            self::ROUND_DOWN => (int) floor($fee),
            default => (int) round($fee),
        };
    }

    protected function plan(): ?Plan
    {
        return TenantContext::get()?->plan;
    }
}
