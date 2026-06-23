# Import / Export CSV & Excel

Kembali ke indeks dokumentasi: `docs/README.md`

## Tujuan

Import master data (produk, customer) dari spreadsheet. Export data ke Excel untuk backup atau analisis lanjutan.

## Fitur Saat Ini

### Export
- **Produk** — barcode, SKU, nama, kategori, harga beli, harga jual, stok, min stok, max stok, tipe pajak, tarif pajak
- **Customer** — nama, telepon, alamat, provinsi, kota, kecamatan, desa, status member, tier, poin
- **Transaksi** — invoice, tanggal, kasir, pelanggan, metode, status, subtotal, diskon, ongkir, PPN, grand total (dapat difilter berdasarkan tanggal & warehouse)

### Import
- **Produk** — upload file Excel/CSV, auto-create kategori jika belum ada, update if barcode exists (updateOrCreate)
- **Customer** — upload file Excel/CSV, validasi kolom wajib

### Template
- Download template Excel kosong dengan header yang sesuai untuk persiapan data

## Route

| Route | Method | Fungsi |
|-------|--------|--------|
| `export.products` | GET | Download Excel produk |
| `export.customers` | GET | Download Excel customer |
| `export.transactions` | GET | Download Excel transaksi (dengan filter) |
| `import.products` | POST | Upload file import produk |
| `import.customers` | POST | Upload file import customer |
| `import.template/{type}` | GET | Download template (products/customers) |

## Permission

| Permission | Untuk apa |
|-----------|-----------|
| `products-export` | Download Excel produk |
| `products-import` | Upload import produk |
| `customers-export` | Download Excel customer |
| `customers-import` | Upload import customer |

## Format Template

### Template Produk
| barcode | sku | nama | deskripsi | kategori | harga_beli | harga_jual | stok | min_stok | max_stok | tipe_pajak | tarif_pajak |
|---------|-----|------|-----------|----------|-----------|-----------|------|----------|----------|-----------|------------|

### Template Customer
| nama | telepon | alamat |
|------|---------|--------|

## Catatan

- Import produk menggunakan `updateOrCreate` berdasarkan barcode — aman untuk re-import
- Kategori otomatis dibuat jika belum ada
- Format file: `.xlsx`, `.xls`, `.csv` (max 5MB)
- Import diproses dalam batch (100 per batch) untuk performa
- Tombol export/import ada di halaman Produk dan Customer
