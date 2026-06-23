# Configuration

Kembali ke indeks dokumentasi: `docs/README.md`

## Environment Penting

| Variable | Untuk apa |
|----------|-----------|
| `APP_URL` | Webhook URL, public invoice, customer portal link, payment callback |
| `APP_VERSION` | Versi aplikasi (tampil di sidebar + POS navbar) |
| `DB_DATABASE` | Nama database (default: `point_of_sales`) |
| `MIDTRANS_SERVER_KEY` | Server key Midtrans |
| `MIDTRANS_CLIENT_KEY` | Client key Midtrans (frontend) |
| `XENDIT_SECRET_KEY` | Secret key Xendit |
| `XENDIT_PUBLIC_KEY` | Public key Xendit |
| `XENDIT_CALLBACK_TOKEN` | Callback token verifikasi webhook Xendit |
| `AUTH_PUBLIC_REGISTRATION` | Aktifkan registrasi publik (`true`/`false`, default: `false`) |
| `WA_SERVICE_URL` | Alamat Node.js WhatsApp service (default: `http://localhost:3001`) |

## APP_URL

`APP_URL` harus public (bukan `localhost`) jika menggunakan:

- Webhook Midtrans/Xendit
- Public invoice sharing
- Customer portal link
- Payment gateway callback

## Payment Gateway

Konfigurasi di `dashboard/settings/payments`:

- **Cash** — tanpa konfigurasi
- **Bank Transfer** — memerlukan rekening bank aktif
- **Midtrans** — memerlukan server key + client key + mode production
- **Xendit** — memerlukan secret key + public key + callback token + mode production

Detail setup: `docs/features/settings-payments.md`

## Bank Accounts

Konfigurasi di `dashboard/settings/bank-accounts`:

- Digunakan untuk pembayaran transfer manual
- Bisa diatur urutan tampilan
- Bisa dinonaktifkan tanpa dihapus

## Tax Settings

Konfigurasi di `dashboard/settings/store` — bagian "Informasi Pajak & Legal":

- **NPWP Toko** — Nomor Pokok Wajib Pajak (format: `XX.XXX.XXX.X-XXX.XXX`)
- **NIB** — Nomor Induk Berusaha
- **Tarif PPN Default** — Persentase PPN untuk produk baru (default: 11.00%)
- Tarif PPN bisa diubah per produk di halaman edit produk

## Printer Settings

Konfigurasi di `dashboard/settings/printer`:

- **Ukuran Kertas** — 80mm atau 58mm
- **Auto-print** — cetak receipt otomatis setelah transaksi (via WebUSB)
- Thermal printer terhubung via WebUSB (browser Chrome/Edge)

## Store Profile

Konfigurasi di `dashboard/settings/store`:

- Nama, alamat, telepon, email, website, kota
- Logo toko
- NPWP dan NIB (untuk keperluan pajak)

## Sales Target

Konfigurasi di `dashboard/settings/target`:

- Target penjualan bulanan
- Muncul di dashboard sebagai progress bar

## WhatsApp Gateway

Konfigurasi di `dashboard/settings/whatsapp`:

- **URL Service** — alamat Node.js service (default `http://localhost:3001`)
- **Aktifkan Gateway** — enable/disable WhatsApp integration
- **Kirim Otomatis** — reminder piutang + invoice otomatis via campaign
- **Koneksi** — scan QR untuk menghubungkan (session tersimpan otomatis)

Detail setup: `docs/features/whatsapp-gateway.md`

## Multi-Warehouse

Konfigurasi di `dashboard/settings/warehouses`:

- **Main Warehouse** — gudang pusat, dibuat otomatis saat seeding
- **Branch Warehouse** — cabang toko yang juga menjual langsung
- **Stock Warehouse** — gudang penyangga (tidak menjual langsung)
- Stok produk dipisah per warehouse di tabel `product_warehouse`

## Catatan Dependency Eksternal

- `laravolt/indonesia` — data provinsi/kota/kecamatan/desa Indonesia
- `barryvdh/laravel-dompdf` — generate PDF invoice/receipt/shipping label
- `picqer/php-barcode-generator` — barcode di dokumen PDF
- `maatwebsite/excel` — import/export CSV + Excel
- Midtrans & Xendit — payment gateway
- `whatsapp-web.js` — WhatsApp gateway (Node.js, terpisah dari Laravel)
