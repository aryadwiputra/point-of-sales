<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Integrasi Bihaju (Partner API)
    |--------------------------------------------------------------------------
    |
    | BPOS bertindak sebagai partner Bihaju. Partner key berlaku platform,
    | merchant key (merchant_api_key) diterima per binding dan disimpan
    | terenkripsi pada baris binding.
    |
    | Dokumen: Bihaju core → docs/api-partner.md
    |

    */

    // Base URL platform Bihaju (tanpa trailing slash).
    'base_url' => env('BIHAJU_BASE_URL', 'https://pos.bihaju.id'),

    // X-Partner-Key: pk_live_... / pk_test_...
    'partner_key' => env('BIHAJU_PARTNER_KEY'),

    // Override per tenant disimpan pada tenant_addons.config / secret.
    'timeout' => (int) env('BIHAJU_TIMEOUT', 10),

    // Rate limit partner di sisi Bihaju: 120 request/menit.
    'throttle' => (int) env('BIHAJU_THROTTLE', 120),

    // Path tetap webhook yang akan dipanggil Bihaju ke BPOS.
    'callback_path' => '/api/bihaju/webhook',

    // Base URL publik BPOS (untuk callback). Kosongkan = APP_URL.
    'callback_base_url' => env('BIHAJU_CALLBACK_BASE_URL'),

    // Saat true, transaksi yang gagal dikirim ke Bihaju TIDAK menggagalkan
    // penjualan — status disimpan sebagai pending/failed dan diretry.
    // Sesuai keputusan: penjualan tunai tidak boleh gagal karena Bihaju.
    'non_blocking' => env('BIHAJU_NON_BLOCKING', true),

    // Percobaan retry pengiriman transaksi.
    'tries' => (int) env('BIHAJU_TRIES', 5),

    // Backoff antar retry (detik).
    'backoff' => [60, 300, 1800, 7200, 21600],
];
