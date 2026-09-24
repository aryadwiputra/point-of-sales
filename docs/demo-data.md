# Demo Data

Kembali ke indeks dokumentasi: `docs/README.md`

## Tujuan

Panduan ini menjelaskan cara mengisi aplikasi dengan dataset demo yang lengkap agar siapa pun yang
melakukan `git clone` dapat langsung mencoba seluruh fitur tanpa menyiapkan data secara manual.

> **Peringatan:** `DemoSeeder` **meregenerasi** data operasional (transaksi, produk, pelanggan, dll).
> Jangan pernah menjalankannya pada database produksi.

## Cara Menjalankan

Setelah `composer install`, `npm install`, dan `php artisan migrate --seed`:

```bash
# Cara 1 — command khusus (merekomendasikan konfirmasi sebelum jalan)
php artisan seed:demo

# Cara 2 — tanpa prompt konfirmasi
php artisan seed:demo --force

# Cara 3 — panggil seeder langsung
php artisan db:seed --class=DemoSeeder --force
```

Semua cara di atas menjalankan rantai seeder yang sama dan mengeset `app_setup_completed = true`,
sehingga aplikasi langsung siap dipakai (tidak perlu melewati wizard `/setup`).

## Akun Demo

| Email | Password | Role | Akses Outlet |
|-------|----------|------|--------------|
| `arya@gmail.com` | `password` | super-admin | Semua outlet (MAL, TKB, PUT) |
| `manager@gmail.com` | `password` | manager | MAL + TKB |
| `cashier@gmail.com` | `password` | cashier | MAL |

Catatan:

- **super-admin** — akses penuh ke seluruh modul dan seluruh outlet.
- **manager** — akses operasional lengkap (produk, stok, purchasing, transaksi, laporan, CRM,
  diskon) lintas outlet yang ditugaskan, tetapi **tanpa** administrasi users/roles/permissions,
  outlet, atau update kredensial payment gateway.
- **cashier** — transaksi POS, buka/tutup shift, tambah pelanggan, bayar piutang/hutang, dan
  memproses pesanan dine-in di outlet MAL.

Semua akun demo dibuat dengan email yang sudah terverifikasi. Email verification kini dinonaktifkan
secara global (lihat `docs/configuration.md`), jadi login langsung masuk ke dashboard.

## Ringkasan Dataset

`DemoSeeder` memanggil beberapa seeder secara berurutan:

| Seeder | Isi |
|--------|-----|
| `DemoOutletSeeder` | Outlet `PUSAT` (non-penjualan), `MAL`, `TKB`, `PUT` + gudang `PUSAT`, `WH-MAL`, `WH-TKB`, `WH-PUT` |
| `UserSeeder` | Tiga akun demo di atas dengan role dan penugasan outlet |
| `SampleDataSeeder` | Pelanggan, supplier, kategori, produk (dengan gambar), transaksi, piutang, voucher, hutang, dan stok awal per gudang |
| `OperationalCoreSeeder` | Shift kasir (histori + aktif), penugasan transaksi ke shift, dan retur penjualan |
| `FeatureCoverageSeeder` | Profil toko, target, pajak, printer, WhatsApp, loyalty, diskon, bank account, payment setting, alur PO→GR→retur supplier, stock opname, dan audit log |
| `FeatureDemoSeeder` | Split payment, unit konversi, batch & FEFO, produk komposit, pricing rule, price list, segment & campaign, stock transfer, dine-in, approval diskon, dan loyalty history |

## Data Per Outlet

- **PUSAT** — gudang pusat, `is_sales_enabled = false`; tidak ada transaksi penjualan.
- **MAL / TKB / PUT** — outlet penjualan dengan gudang cabang masing-masing.
- Stok cabang terisi (default 25 unit per produk), lalu `products.stock` disinkronkan sebagai
  agregat global.
- Skenario multi-outlet/multi-gudang lebih lengkap ada di `docs/getting-started.md` bagian
  **Skenario Multi-Cabang**.

## Reset Dataset

Jalankan ulang salah satu perintah di atas. Seeder bersifat idempoten untuk entitas utama
(outlet, user, produk) dan akan meregenerasi transaksi/operasional terkait.

## Seeder Produksi vs Demo

| Seeder | Aman untuk produksi? | Isi |
|--------|----------------------|-----|
| `DatabaseSeeder` (default `migrate --seed`) | Ya | Permission, role, payment setting, dine-in setting, gudang `PUSAT` |
| `DemoSeeder` / `seed:demo` | Tidak | Dataset demo lengkap + akun demo |
