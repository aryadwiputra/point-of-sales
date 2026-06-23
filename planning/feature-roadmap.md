# Feature Roadmap v2.x — Point of Sales

## Overview

Roadmap fitur untuk membawa POS ini dari mature menuju **enterprise-ready, open-source POS terlengkap untuk UMKM Indonesia**. Setiap tier diurutkan berdasarkan dampak bisnis vs effort.

---

## Tier 1: High Impact — Rilis v2.1

### 1. PPN/PPh Tax Management

#### Objective
Mendukung PPN 11% (dan tarif lainnya) untuk transaksi, faktur pajak sederhana, serta NPWP/NIB pada profil toko.

#### Why
Wajib untuk UMKM dengan omzet > 4,8M/thn. Invoice tanpa PPN tidak sah secara perpajakan.

#### Key Features
- Tax setting per produk (exclusive/inclusive, tax rate)
- PPN otomatis di grand_total (inclusive: tax-in-price, exclusive: add tax)
- Baris PPN pada invoice, receipt, thermal print
- NPWP dan NIB di profil toko
- Laporan PPN masa sederhana

#### Database Impact
- `products`: tambah `tax_type` (exclusive/inclusive), `tax_rate` (decimal)
- `transactions`: tambah `tax_rate`, `tax_total`, `customer_npwp`
- `settings`: tambah `store_npwp`, `store_nib`

#### Permission Impact
- None — semua user bisa lihat, tax adalah properti produk

#### Files Affected
~20: 2 migrations, 1 service (TaxService), 2 controller modif, 4 frontend modif, 2 PDF blade modif

#### Effort
3-4 hari

---

### 2. Import/Export CSV + Excel

#### Objective
Import master data (produk, customer, supplier) dari CSV/Excel. Export laporan ke spreadsheet.

#### Why
Dealbreaker untuk adopsi. Input 1000 produk manual 1 per 1 tidak feasible.

#### Key Features
- Import produk (dengan template contoh)
- Import customer
- Export produk, customer, supplier ke Excel
- Export laporan (sales, profit, stock mutation)
- Validasi error per baris + preview

#### Database Impact
- None (hanya operasi baca/tulis existing table)

#### Permission Impact
- `products-import`, `products-export`
- `customers-import`, `customers-export`
- `reports-export`

#### Technical
- Install `maatwebsite/laravel-excel`
- `app/Exports/` — ProductsExport, CustomersExport, TransactionsExport
- `app/Imports/` — ProductsImport, CustomersImport (with validation)
- Controller: ImportController (POST), ExportController (GET)

#### Effort
3-4 hari

---

### 3. Unit Conversion (Multi-Satuan)

#### Objective
Satu produk dengan multiple satuan — pcs, box, karton, kg — dengan harga dan konversi stok otomatis.

#### Why
Standar POS Indonesia. Beras dijual per kg dan per karung, sabun per pcs dan per karton. Ini ekspektasi dasar.

#### Key Features
- Master unit (pcs, box, karton, kg, liter, meter)
- Produk memiliki base unit + additional units dengan conversion factor
- Harga berbeda per unit
- Stok dikelola di base unit, otomatis dikonversi
- Barcode per unit
- POS checkout: pilih unit sebelum add to cart

#### Database Impact
- `units` table (id, name, symbol)
- `product_units` pivot (product_id, unit_id, is_base, conversion_factor, buy_price, sell_price, barcode)
- `carts`: tambah `unit_id`, `conversion_factor`
- `transaction_details`: tambah `unit_id`, `unit_label`

#### Permission Impact
- None

#### Effort
4-5 hari

---

### 4. Reorder Point + Auto-PO Suggestion

#### Objective
Configurable minimum stock per produk + auto-suggestion untuk restock.

#### Why
Mencegah stock-out. Fitur yang bedain dari POS kalkulator.

#### Key Features
- `min_stock` dan `max_stock` per produk (dan per warehouse)
- Dashboard widget "Produk Perlu Restock"
- Dashboard notifikasi berubah dari `stock <= 0` jadi `stock <= min_stock`
- Tombol "Buat Draft PO" dari list — pre-populate items
- Badge count di sidebar

#### Database Impact
- `products`: tambah `min_stock`, `max_stock`
- `product_warehouse`: tambah `min_stock`, `max_stock`

#### Effort
2-3 hari

---

## Tier 2: High Value — Rilis v2.2

### 5. Discount Approval Workflow

#### Objective
Diskon melebihi threshold memerlukan approval supervisor sebelum transaksi selesai.

#### Why
Kritis untuk toko dengan banyak kasir — mencegah fraud dan human error.

#### Key Features
- Configurable discount threshold (nominal atau persentase)
- Supervisor PIN atau password confirmation
- Notifikasi ke supervisor saat diskon perlu approval
- Audit trail untuk setiap approved/denied discount
- Auto-deny jika tidak ada response dalam X menit

#### Database Impact
- `settings`: tambah `discount_approval_threshold`
- `transactions`: tambah `discount_approved_by`, `discount_approved_at`
- New `discount_approval_logs` table

#### Permission Impact
- `discounts-approve`

#### Effort
2-3 hari

---

### 6. Multi-Price List

#### Objective
Harga berbeda per kelompok pelanggan — retail, grosir, member, reseller.

#### Why
Simple price list yang tidak memerlukan pricing rules engine yang kompleks.

#### Key Features
- Price list CRUD (nama, kelompok pelanggan)
- Harga spesifik per produk dalam price list
- POS checkout: pilih price list customer → auto-apply harga
- Integrasi dengan customer segment
- Fallback ke sell_price jika tidak ada di price list

#### Database Impact
- `price_lists` table (name, customer_scope)
- `price_list_items` table (price_list_id, product_id, price)
- `transactions`: tambah `price_list_id`

#### Effort
3-4 hari

---

### 7. Batch / Expiry Date Tracking

#### Objective
Lacak batch number + expired date per penerimaan barang. FEFO (First Expiry First Out) saat checkout.

#### Why
Kritis untuk FMCG, makanan, minuman, obat, dan kimia.

#### Key Features
- Batch number + expired date input saat goods receiving
- Stock tracking per batch di product_warehouse level
- FEFO logic saat checkout — pick item dengan expired date terdekat
- Dashboard peringatan barang mendekati expired (30/7/0 hari)
- Stock opname per batch

#### Database Impact
- `product_batches` table (product_id, warehouse_id, batch_number, expired_at, stock, received_at)
- Stock mutation: tambah batch reference
- Checkout: create transaction detail dengan batch_id

#### Effort
4-5 hari

---

### 8. Composite Products / Kits

#### Objective
Produk bundle dari komponen — "Paket Hemat (shampoo + sabun + sikat gigi)". Stok komponen otomatis berkurang saat kit terjual.

#### Key Features
- Composite product definition (nama, SKU, barcode, harga, komponen)
- Komponen: product_id + qty
- Stok: composite tidak punya stok sendiri, stok = min komponen
- Checkout: kurangi stok setiap komponen
- Bom/recipe: bisa pecah qty menjadi proporsi
- Barcode scan kit → otomatis tambah semua komponen

#### Database Impact
- `composite_products` table (id, product_id)
- `composite_product_items` table (composite_id, component_product_id, qty)
- Products: tambah `is_composite` flag

#### Effort
3-4 hari

---

## Tier 3: Strategic — Rilis v2.3+

### 9. Customer Portal (Self-Service)

#### Objective
Pelanggan bisa lihat invoice, riwayat transaksi, status piutang, dan bayar online.

#### Why
Kurangi beban admin. Tingkatkan collection rate.

#### Key Features
- Public route dengan token akses (di invoice)
- Lihat invoice + riwayat transaksi
- Status piutang + aging
- Bayar piutang via Midtrans/Xendit (existing gateway)
- Download PDF invoice
- Daftar/register dari portal

#### Security
- Token akses di-include di URL invoice
- Token bisa di-reset oleh admin
- No sensitive data tanpa autentikasi

#### Effort
4-5 hari

---

### 10. Mobile POS / Tablet Optimization

#### Objective
UI touch-friendly untuk tablet Android/iOS via browser. Barcode scanning via kamera.

#### Why
Banyak UMKM pakai tablet sebagai POS station — murah dan portable.

#### Key Features
- Touch-friendly UI (min 44px touch targets — existing)
- Barcode scanner via kamera (Camera API)
- Offline-ready (lihat no. 12)
- PWA manifest (add to home screen)
- Fullscreen mode (hide browser chrome)

#### Technical
- Install `html5-qrcode` atau `zbar-wasm` untuk barcode scanner
- PWA: manifest.json + service worker
- CSS: existing touch target utilities sudah ada

#### Effort
3-4 hari

---

### 11. Offline Mode

#### Objective
Transaksi tetap berjalan saat internet putus. Queue transaksi offline, sync saat online.

#### Why
Kills competitors. Banyak lokasi UMKM dengan internet tidak stabil.

#### Key Features
- Service worker cache API responses (produk, customer, pricing)
- IndexedDB — cache produk, customer, pricing rules
- Queue transaksi offline
- Background sync saat online
- Conflict resolution (sync timestamps)
- UI indicator "Offline Mode"

#### Technical
- Register service worker
- Cache-first strategy untuk master data
- Network-first untuk transaksi (fallback ke offline queue)
- Background Sync API untuk submit transaksi

#### Effort
5-7 hari

---

### 12. Thermal Printer Integration

#### Objective
Auto-print receipt ke thermal printer (ESC/POS protocol) setelah checkout.

#### Why
UMKM pakai thermal printer murah (80mm/58mm). Ini ekspektasi, bukan fitur tambahan.

#### Key Features
- WebUSB / WebSerial untuk connect printer dari browser
- Auto-print setelah transaksi berhasil
- Config (paper width 80mm/58mm, charset)
- Print preview sebelum kirim ke printer
- Fallback ke PDF receipt jika printer tidak terdeteksi

#### Technical
- WebUSB API (Chrome/Edge) untuk ESC/POS printers
- Library: `escpos-php` (server-side) atau `web-escpos` (client-side)
- Cek ketersediaan WebUSB di browser

#### Effort
3-4 hari

---

### 13. Marketplace Integration

#### Objective
Sinkronisasi produk dan stok ke marketplace (Tokopedia, Shopee, Lazada).

#### Why
Ekosistem. Toko offline + online pakai stok yang sama.

#### Key Features
- Export produk ke marketplace via API
- Import order dari marketplace
- Sinkronisasi stok otomatis (cron job)
- Mapping kategori marketplace ↔ kategori lokal
- Log sinkronisasi

#### Technical
- OpenAPI spec per marketplace
- Queue job untuk sinkronisasi
- Webhook handler untuk order masuk

#### Effort
5-7 hari per marketplace

---

### 14. Multi-Currency

#### Objective
Support IDR, USD, SGD, MYR, dll dengan kurs harian.

#### Why
Untuk daerah perbatasan, turis, atau bisnis yang bertransaksi dalam valas.

#### Key Features
- Currency master (code, symbol, exchange_rate)
- Konversi otomatis di checkout (pilih currency)
- Laporan dalam IDR (base currency) + currency asal
- Kurs harian bisa diupdate manual atau via API

#### Database Impact
- `currencies` table (code, name, symbol, exchange_rate, is_base)
- `transactions`: tambah `currency_code`, `exchange_rate`

#### Effort
2-3 hari

---

## Recommended Release Plan

| Release | Modules | Timeline |
|---------|---------|----------|
| **v2.0** | Multi-warehouse (✅ done) | Sekarang |
| **v2.1** | PPN Tax, Import/Export, Unit Conversion, Reorder Point | ~2 minggu |
| **v2.2** | Discount Approval, Multi-Price List, Batch/Expiry, Composite Products | ~2 minggu |
| **v2.3** | Customer Portal, Mobile POS (PWA) | ~2 minggu |
| **v2.4** | Offline Mode, Thermal Printer | ~2 minggu |
| **v3.0** | Marketplace Integration, Multi-Currency | ~3 minggu |

Setiap release backward compatible dan bisa dirilis sendiri tanpa menunggu yang lain.
