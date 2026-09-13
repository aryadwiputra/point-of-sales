# Offline Mode

Offline mode membantu kasir menyelesaikan checkout yang sudah disiapkan ketika koneksi sementara terputus. Fitur ini bukan mode POS penuh tanpa server.

## Perilaku

- Master data tertentu tersedia melalui cache-first PWA.
- Transaksi checkout dimasukkan ke IndexedDB `pending_transactions` ketika offline.
- Queue dikirim ke `POST /api/v1/pos/transactions/sync` saat koneksi kembali.
- `client_uuid` menjaga sinkronisasi tetap idempotent.
- Server tetap menjadi sumber harga dan validasi akhir.

## Batasan

- Cart utama berbasis server, sehingga menambahkan produk baru saat offline terbatas.
- Konflik stok atau perubahan harga diselesaikan oleh validasi server ketika queue flush.

## File Terkait

- `resources/js/Utils/offlineDb.js`
- `app/Http/Controllers/Api/PosApiController.php`
- `docs/testing-manual.md`
