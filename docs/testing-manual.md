# Manual Testing Checklist

Checklist pengujian manual yang membutuhkan perangkat/kredensial nyata (tidak bisa diotomasi dengan test suite). Jalankan setiap kali rilis besar menyentuh area terkait.

Kembali ke indeks dokumentasi: `docs/README.md`

## 1. QRIS End-to-End (Sandbox)

**Prasyarat:** Midtrans atau Xendit aktif di Settings > Payment dengan kredensial sandbox, `APP_URL` publik (webhook butuh URL publik — localhost tidak menerima callback).

- [ ] Checkout POS dengan metode **QRIS** → redirect ke halaman print, QR tampil
- [ ] Scan QR dengan aplikasi e-wallet sandbox (GoPay/ShopeePay sandbox Midtrans, atau Xendit simulation)
- [ ] Status transaksi berubah `pending` → `paid` otomatis (polling ±4 detik) tanpa reload manual
- [ ] Struk tercetak menampilkan status `LUNAS` setelah pembayaran
- [ ] Webhook masuk: `payment_status` ter-update di DB + `payment_reference` terisi
- [ ] Transaksi QRIS yang **belum dibayar** tidak tercetak otomatis (auto-print skip saat pending)

## 2. ESC/POS Printer Asli

**Prasyarat:** Printer thermal 58mm/80mm USB (Chromium browser: Chrome/Edge/Opera).

- [ ] Settings > Printer → **Hubungkan Printer** → pilih device printer di prompt WebUSB
- [ ] **Test Print** → struk dummy tercetak, terpotong rapi (auto cut), lebar sesuai paper size
- [ ] **Buka Laci (Kick)** → cash drawer terbuka (jika terhubung via printer, pin 2)
- [ ] **Putuskan** → koneksi lepas, tombol kembali ke "Hubungkan Printer"
- [ ] Checkout tunai dengan auto-print aktif → struk tercetak otomatis + drawer kick
- [ ] Checkout non-tunai (QRIS/bank transfer) → auto-print tanpa drawer kick
- [ ] Ganti paper size 58mm ↔ 80mm → lebar struk menyesuaikan (32 vs 48 karakter)
- [ ] Browser non-Chromium (Firefox/Safari) → tombol WebUSB tidak muncul error, cetak manual via `window.print()` tetap berfungsi

## 3. Offline Sync

**Prasyarat:** POS terbuka, shift kasir aktif, produk sudah ter-cache (buka POS online dulu).

- [ ] Matikan koneksi (devtools offline / cabut WiFi) → banner amber "Transaksi disimpan offline" muncul
- [ ] Tambah produk ke keranjang **saat offline** → gagal dengan toast yang jelas (keranjang server-side)
- [ ] Checkout dengan keranjang yang sudah terisi sebelum offline → transaksi masuk antrean IndexedDB, indikator "N transaksi menunggu sinkronisasi" tampil
- [ ] Nyalakan kembali koneksi → transaksi terkirim otomatis, toast sukses per item
- [ ] Refresh halaman POS dengan antrean masih ada → flush ulang saat mount
- [ ] Antrean dengan shift **sudah ditutup** → item gagal sync dengan alasan "Shift kasir belum dibuka" dan tetap di antrean
- [ ] Stok berkurang sesuai item yang tersinkron; harga di server dipakai (bukan harga klien)
- [ ] Kirim antrean yang sama dua kali (double flush) → tidak ada duplikat transaksi (idempotency `client_uuid`)

## Menu Modifiers and Non-Stocked Items

- [ ] Create a `single` group with a required option and attach it to a product; the POS grid opens the picker instead of adding directly.
- [ ] Create a `multiple` group with `max_select = 2`; the third option is disabled.
- [ ] Set a per-product override; the modal total and the cart line use the override, not the global delta.
- [ ] Add the same product twice with different options; two separate cart lines appear and both survive a hold/resume.
- [ ] Add two lines with identical options and note; they merge into one line with quantity 2.
- [ ] Check out and verify the thermal receipt (58 mm and 80 mm), the web print page, and the PDF receipt list each option and the item note.
- [ ] Create a product with `track_stock = false` and zero stock; it remains orderable from POS and the dine-in QR menu, and stock is not decremented.
- [ ] Run a dine-in self-order with modifiers, accept it in the dashboard, and confirm the created transaction shows the options and note.
- [ ] Queue a sale offline with modifiers, reconnect, and confirm the synced transaction keeps the options and note.
