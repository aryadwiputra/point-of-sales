# Getting Started

Kembali ke indeks dokumentasi: `docs/README.md`

## Tujuan

Panduan ini membantu developer baru menjalankan aplikasi dari nol sampai bisa login dan mengakses modul dashboard.

## Requirement Minimum

- PHP 8.3+ sesuai kebutuhan Laravel 13
- Composer
- Node.js 18+ + npm
- MySQL / MariaDB
- ekstensi PHP standar Laravel
 - Chrome/Chromium (untuk WhatsApp Gateway — opsional)

 Untuk deployment production yang memakai automation, siapkan queue worker dan scheduler Laravel.
 Jalankan `php artisan schedule:run` setiap menit. WhatsApp Gateway juga memerlukan service Node
 terpisah dan process manager seperti PM2.

## Langkah Setup

```bash
cp .env.example .env
composer install
PUPPETEER_SKIP_DOWNLOAD=true npm install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
# Start all local processes
composer run dev
# Open http://localhost:8000 after the server starts
```

## Urutan Bootstrapping yang Disarankan

1. isi konfigurasi database di `.env`
2. jalankan `php artisan migrate --seed`
3. jalankan `php artisan storage:link`
4. jalankan `composer run dev`
5. buka `http://localhost:8000`; pada instalasi pertama aplikasi otomatis membuka wizard `/setup`
6. selesaikan wizard untuk membuat akun admin, profil toko, kategori, dan gudang utama
7. login menggunakan akun yang dibuat pada wizard setup

## Seed Data

`DatabaseSeeder` hanya akan membuat:

- permission
- role
- payment setting awal
- pengaturan dine-in
- warehouse utama `PUSAT`

Tidak ada user default atau sample data pada seeder utama. Pada instalasi pertama, buka root aplikasi dan wizard `/setup` akan terbuka otomatis untuk membuat akun admin.

Untuk data demo/test secara eksplisit:

```bash
php artisan db:seed --class=UserSeeder
php artisan db:seed --class=SampleDataSeeder
```

`SampleDataSeeder` membutuhkan `UserSeeder` terlebih dahulu. Seeder tambahan untuk coverage operasional tersedia sebagai `OperationalCoreSeeder`, `FeatureCoverageSeeder`, dan `FeatureDemoSeeder`.

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
 - reminder, reorder, atau campaign tidak berjalan: cek queue worker dan `schedule:run`
 - WhatsApp tidak terkirim: cek `WA_SERVICE_URL`, status device, `wa_enabled`, dan service Node
