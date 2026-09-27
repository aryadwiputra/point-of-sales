# Thermal Printer

Kembali ke indeks dokumentasi: `docs/README.md`

## Tujuan

Cetak receipt ke printer thermal (ESC/POS protocol) langsung dari browser via WebUSB, atau melalui server-side text generation.

## Fitur Saat Ini

### ThermalPrintService (Server-side)
- Generate teks receipt dalam format monospace
- Support 80mm (48 karakter) dan 58mm (32 karakter)
- Format: header toko, invoice info, item list, subtotal, diskon, PPN, total, pembayaran, footer
- Output: plain text (`generateReceiptText`) dan HTML (`generateReceiptHtml`)

### Thermal Print Route
- `GET /dashboard/documents/transactions/{invoice}/print/thermal` — HTML receipt
- Dipakai sebagai fallback browser-print jika printer ESC/POS tidak tersedia atau gagal

### Printer Settings
- Paper size: 80mm / 58mm
- Auto-print toggle (cetak otomatis setelah transaksi)

## ESC/POS WebUSB (Client-side)

Cetak langsung byte ESC/POS ke printer via WebUSB (tanpa dialog print browser):

- Generator byte: `resources/js/Utils/escpos.js` — `buildReceiptBytes(data, paperSize)` (58mm/80mm, auto cut `GS V`), `drawerKickBytes(pin)` (ESC p untuk buka cash drawer)
- Connector: `requestPrinter()` / `getPrinter()` / `printBytes()` — WebUSB, filter device printer class 7
- Tombol di **Settings > Printer**: Hubungkan Printer, Test Print, Buka Laci (Kick), Putuskan
- Tombol **Thermal** pada halaman struk mencoba ESC/POS terlebih dahulu, lalu membuka dialog print browser jika gagal
- **Auto-print** setelah checkout non-QRIS (jika setting auto-print aktif + printer sudah terhubung + status bukan pending) + drawer kick otomatis untuk pembayaran tunai (`Print.jsx`)
- Perangkat dan endpoint USB disimpan setelah koneksi berhasil agar Test Print dan halaman struk memakai printer yang sama

> **Catatan:** WebUSB hanya tersedia di browser Chromium (Chrome/Edge/Opera) dan butuh gesture pengguna (klik tombol "Hubungkan Printer"). Browser lain, printer yang tidak kompatibel, atau printer yang terputus akan memakai fallback `window.print()` dari tombol Thermal. Auto-print tidak membuka dialog browser secara otomatis.

## Route

| Route | Method | Fungsi |
|-------|--------|--------|
| `pdf.transactions.thermal` | GET | HTML receipt thermal |
| `settings.printer` | GET | Halaman settings printer |
| `settings.printer.update` | POST | Simpan settings printer |

## Format Receipt (80mm)

```
            TOKO ANDA
        Jl. Contoh No. 123
        Telp: 021-123456
--------------------------------
No: TRX-XXXXXXXXXX
Tgl: 22/06/2026 14:30
Kasir: Arya
Pelanggan: Umum
--------------------------------
Produk A
2x @ 10.000          20.000
Produk B
1x @ 15.000          15.000
--------------------------------
Subtotal             35.000
PPN                   3.850
--------------------------------
TOTAL                38.850
Tunai                50.000
Kembali              11.150
--------------------------------
        Terima kasih
```

## Catatan

- Untuk print via jaringan: gunakan `NetworkPrintConnector` atau `WindowsPrintConnector` (belum ada di codebase)
- Print shift X/Z report juga memakai ThermalPrintService (`generateShiftReportText`)
