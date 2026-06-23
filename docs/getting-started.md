# Getting Started

Kembali ke indeks dokumentasi: `docs/README.md`

## Tujuan

Panduan ini membantu developer baru menjalankan aplikasi dari nol sampai bisa login dan mengakses modul dashboard.

## Requirement Minimum

- PHP 8.2+ sesuai kebutuhan Laravel 12
- Composer
- Node.js 18+ + npm
- MySQL / MariaDB
- ekstensi PHP standar Laravel
- Chrome/Chromium (untuk WhatsApp Gateway — opsional)

## Langkah Setup

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run dev
php artisan serve
```

## Urutan Bootstrapping yang Disarankan

1. isi konfigurasi database di `.env`
2. jalankan `php artisan migrate --seed`
3. jalankan `php artisan storage:link`
4. jalankan frontend dengan `npm run dev`
5. jalankan server aplikasi
6. login menggunakan akun default

## Default Login

- Admin: `arya@gmail.com` / `password`
- Kasir: `cashier@gmail.com` / `password`

## Seed Data

Seeder utama akan membuat:

- permission
- role
- user default
- payment setting awal
- sample data operasional

Catatan penting:

- fitur yang bergantung pada permission baru sebaiknya selalu diuji setelah `db:seed`
- jika permission terlihat tidak sinkron, logout-login ulang setelah seed selesai

## Setelah Aplikasi Jalan

Cek minimal:

1. `dashboard/settings/store`
2. `dashboard/settings/payments`
3. `dashboard/settings/bank-accounts`
4. `dashboard/settings/target`

## Tips Validasi Cepat

- buka dashboard utama
- buka transaksi kasir
- cek histori transaksi
- cek stock opname / cashier shift / audit logs jika migration fiturnya sudah ada

## Error Umum

- gambar tidak tampil: jalankan `php artisan storage:link`
- payment webhook tidak jalan: cek `APP_URL`
- modul baru error 500: cek apakah migration fitur sudah dijalankan
