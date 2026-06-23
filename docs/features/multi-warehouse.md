# Multi-Warehouse

Kembali ke indeks dokumentasi: `docs/README.md`

## Tujuan

Memisahkan stok produk per lokasi fisik (gudang pusat, cabang toko, gudang penyangga). Memungkinkan bisnis dengan >1 lokasi operasional.

## Definisi

| Istilah | Arti |
|---------|------|
| Main Warehouse | Gudang utama, dibuat otomatis saat seeding |
| Branch Warehouse | Cabang toko yang juga menjual langsung |
| Stock Warehouse | Gudang penyangga (tidak menjual langsung) |

## Fitur Saat Ini

### Warehouse CRUD
- Tambah, edit, hapus warehouse
- Tipe: main, branch, warehouse
- Status aktif/nonaktif
- Urutan tampilan
- Guard: tidak bisa hapus warehouse yang masih punya stok
- Guard: tidak bisa hapus warehouse utama

### Product-Warehouse Pivot
- Stok disimpan per produk per warehouse di `product_warehouse`
- Saat warehouse baru dibuat, semua produk otomatis ter-sync dengan stok 0
- Saat seeder, semua stok produk existing dipindah ke warehouse PUSAT

### Warehouse di Shift
- Kasir memilih warehouse saat buka shift
- Warehouse tidak bisa diubah setelah shift dibuka
- Admin bisa lihat warehouse asal di detail shift

### Warehouse di Transaksi
- Produk yang tampil di POS hanya yang punya stok > 0 di warehouse shift aktif
- Cart menyimpan `warehouse_id`
- Checkout decrement stok di pivot warehouse
- Transaksi tercatat dengan `warehouse_id`
- Search product by barcode — hanya produk yang ada di warehouse shift aktif

### Warehouse di Purchasing
- PO punya `warehouse_id` (tujuan gudang)
- GR auto-inherit warehouse dari PO
- Supplier Return: stok decrement dari warehouse asal
- Stock Opname: pilih warehouse, baca stok dari pivot warehouse

### Stock Transfer Antar Warehouse
- Transfer antar warehouse (source → destination)
- Status: draft → in_transit → completed / cancelled
- Send: kurangi stok source + catat stock mutation
- Receive: tambah stok destination + catat stock mutation
- Cancel: jika in_transit, stok dikembalikan ke source
- Validasi stok cukup sebelum send

## Halaman dan Route

| Route | Fungsi |
|-------|--------|
| `settings.warehouses.index` | Daftar warehouse (CRUD inline) |
| `stock-transfers.index` | Daftar transfer stok |
| `stock-transfers.create` | Buat transfer baru |
| `stock-transfers.show` | Detail transfer + action (send/receive/cancel) |

## Permission

| Permission | Untuk apa |
|-----------|-----------|
| `warehouses-access` | Lihat daftar warehouse |
| `warehouses-create` | Tambah warehouse baru |
| `warehouses-update` | Edit warehouse |
| `warehouses-delete` | Hapus warehouse |
| `stock-transfers-access` | Lihat daftar transfer |
| `stock-transfers-create` | Buat transfer |
| `stock-transfers-send` | Kirim transfer (decrement source) |
| `stock-transfers-receive` | Terima transfer (increment dest) |
| `stock-transfers-cancel` | Batalkan transfer |

## Alur User

1. Admin: setup warehouse di Settings → Gudang
2. Cashier: buka shift → pilih warehouse
3. POS: hanya produk dengan stok di warehouse shift yang tampil
4. Checkout: stok decrement dari warehouse shift
5. PO: tentukan warehouse tujuan
6. GR: barang masuk ke warehouse PO
7. Stock Opname: pilih warehouse, hitung stok fisik
8. Stock Transfer: kirim barang antar warehouse

## Catatan Teknis

- Semua tabel stok & transaksi punya `warehouse_id` nullable (backward compat)
- Jika `warehouse_id` null, fallback ke `products.stock` (legacy single-warehouse)
- Seed data: warehouse PUSAT (main) dibuat otomatis, stok existing dipindah ke pivot
