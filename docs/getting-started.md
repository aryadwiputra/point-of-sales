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

Tidak ada user default atau sample data pada seeder utama. Outlet `PUSAT` dibuat sebagai gudang pusat non-penjualan. Pada instalasi pertama, buka root aplikasi dan wizard `/setup` akan terbuka otomatis untuk membuat akun admin.

Untuk dataset demo lengkap secara eksplisit:

```bash
php artisan db:seed --class=DemoSeeder --force
```

`DemoSeeder` membuat outlet demo `MAL`, `TKB`, dan `PUT`, user demo, produk, transaksi, shift, purchasing, inventory, pricing, dine-in, dan feature coverage. Transaksi penjualan hanya dibuat pada gudang outlet penjualan; tidak ada panggilan gateway pembayaran nyata.

Akun demo yang dibuat:

| Email | Password | Role | Outlet |
|-------|----------|------|--------|
| `arya@gmail.com` | `password` | super-admin | Semua |
| `manager@gmail.com` | `password` | manager | MAL + TKB |
| `cashier@gmail.com` | `password` | cashier | MAL |

Rincian lengkap dataset demo ada di `docs/demo-data.md`.

Alias kompatibilitas berikut juga tersedia:

```bash
php artisan seed:demo --force
```

`DatabaseSeeder` aman untuk instalasi/produksi. Jangan menjalankan `DemoSeeder` atau `seed:demo` pada database produksi karena data operasional demo akan diregenerasi.

Catatan penting:

- fitur yang bergantung pada permission baru sebaiknya selalu diuji setelah `db:seed`
- jika permission terlihat tidak sinkron, logout-login ulang setelah seed selesai

## Setelah Aplikasi Jalan

Cek minimal:

1. `dashboard/settings/store`
2. `dashboard/settings/payments`
3. `dashboard/settings/bank-accounts`
4. `dashboard/settings/target`

## Skenario Multi-Cabang

Jika toko memiliki lebih dari satu cabang, gunakan skenario ini:

1. Saat instalasi pertama (`/setup`), isi minimal satu cabang pada langkah "Cabang". Contoh untuk Cafe UD Djaya:
   - `PUSAT` (pusat) sudah dibuat otomatis sebagai gudang pusat non-penjualan.
   - `MAL` / Malabar (cabang penjualan)
   - `TKB` / Taman Kencana (cabang penjualan)
   - `PUT` / Puter (cabang penjualan)
2. Akun Super Admin otomatis terasosiasi ke setiap outlet; kasir baru dibuat lewat `Pengguna` dengan outlet assignment dan satu default outlet.
3. Stok awal cabang: transfer dari `PUSAT → WH-MAL/WH-TKB/WH-PUT` lewat menu **Stock Transfer** atau isi lewat **Stock Opname**.
4. Pengaturan per-cabang (logo, struk, payment gateway, bank account, printer, WhatsApp, target) ada di halaman Settings dengan outlet switcher di navbar.
5. Tutup shift sebelum pindah outlet — selector outlet terkunci selama shift kasir aktif.

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
