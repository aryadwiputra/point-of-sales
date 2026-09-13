# POS & Transactions

Kembali ke indeks dokumentasi: `docs/README.md`

## Daftar Isi

- Tujuan
- Fitur Saat Ini
- Halaman dan Route
- Permission
- Alur User
- Integrasi Data
- Batasan Saat Ini
- File Sentral

## Tujuan

Menyediakan alur kasir cepat untuk pencarian produk, pengelolaan cart, checkout, hold/resume, dan distribusi dokumen transaksi.

## Fitur Saat Ini

- cari produk via barcode / pencarian
- cart multi-item
- update qty cart
- hold transaction
- resume held cart
- clear held cart
- checkout tunai, split payment (maksimal dua tender), bank transfer, Midtrans, Xendit, pay later
- print invoice / receipt / shipping label
- share invoice publik
- add customer langsung dari POS

## Halaman dan Route

- `dashboard/transactions`
- `dashboard/transactions/history`
- `transactions.searchProduct`
- `transactions.addToCart`
- `transactions.updateCart`
- `transactions.destroyCart`
- `transactions.hold`
- `transactions.resume`
- `transactions.clearHold`
- `transactions.held`
- `transactions.store`
- `transactions.print`
- `transactions.public`

## Permission

- `transactions-access`

Operasi transaksional tertentu juga mewajibkan middleware `active_shift`.

## Alur User

1. kasir membuka halaman transaksi
2. jika shift aktif, kasir dapat cari produk dan membangun cart
3. cart dapat di-hold lalu di-resume
4. checkout membuat transaksi, detail, profit, dan pengurangan stok
5. jika `pay_later`, sistem membuat receivable
6. user diarahkan ke dokumen print / invoice

## Integrasi Data

- `transactions`
- `transaction_details`
- `profits`
- `receivables`
- `bank_accounts`
- `payment_settings`

## Batasan Saat Ini

- split payment tidak tersedia saat offline; refund split pada versi ini dicatat sebagai refund tunai

- operasi cart dan checkout bergantung pada shift aktif
- payment gateway bergantung pada konfigurasi valid
- checkout masih menjadi pusat perubahan stok penjualan

## File Sentral

- `routes/web.php`
- `app/Http/Controllers/Apps/TransactionController.php`
- `resources/js/Pages/Dashboard/Transactions`
