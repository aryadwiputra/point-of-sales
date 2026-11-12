<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Finance (Aplikasi Akuntansi Terpisah)
    |--------------------------------------------------------------------------
    |
    | BPOS adalah sumber data operasional; aplikasi Finance menampung COA,
    | jurnal/GL, periode fiskal, dan laporan keuangan. Keduanya terhubung
    | melalui SSO (token bertanda tangan) dan jembatan event.
    |
    */

    // URL dasar aplikasi Finance (tanpa trailing slash).
    'url' => env('FINANCE_URL', 'http://localhost:8001'),

    // Secret bersama untuk menandatangani/memeriksa token SSO.
    // Bila kosong, jatuh ke APP_KEY (hanya untuk dev).
    'sso_secret' => env('FINANCE_SSO_SECRET'),

    // Secret bersama untuk menandatangani/memeriksa event bridge
    // (BPOS → aplikasi Finance), dipakai sebagai HMAC-SHA256 pada raw body.
    'bridge_secret' => env('FINANCE_BRIDGE_SECRET'),

    // TTL token SSO dalam detik.
    'sso_ttl' => (int) env('FINANCE_SSO_TTL', 300),

    // Timeout HTTP untuk panggilan keluar ke aplikasi Finance.
    'timeout' => (int) env('FINANCE_TIMEOUT', 10),
];
