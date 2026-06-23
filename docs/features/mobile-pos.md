# Mobile POS / PWA

Kembali ke indeks dokumentasi: `docs/README.md`

## Tujuan

Akses POS dari tablet/handphone dengan barcode scanner via kamera, install ke home screen, dan fullscreen mode.

## Fitur Saat Ini

### Camera Barcode Scanner
- Library: `html5-qrcode`
- Scan barcode via kamera belakang (environment-facing)
- Auto-search produk setelah scan
- Modal fullscreen scanner — buka kamera, scan, tutup otomatis
- Tombol kamera di search bar POS

### Progressive Web App (PWA)
- `manifest.json` — name, icons, display standalone, theme color
- `sw.js` — service worker untuk cache asset + API master data
- Theme-color meta tag
- Install prompt ke home screen (Android Chrome)
- Support offline (cache-first untuk master data)

### Fullscreen Mode
- Tombol fullscreen di header POS
- Sembunyikan browser chrome
- Toggle masuk/keluar fullscreen

### Touch Optimization
- Semua interactive element: `min-h-touch` (44px) dan `min-w-touch`
- Gap aman antar tombol
- Layout responsive untuk tablet landscape

## File Terkait

| File | Fungsi |
|------|--------|
| `resources/js/Components/POS/BarcodeScanner.jsx` | Camera scanner component |
| `public/manifest.json` | PWA manifest |
| `public/sw.js` | Service worker |
| `resources/js/app.jsx` | SW registration |
| `resources/js/Layouts/POSLayout.jsx` | Fullscreen toggle |
| `resources/js/Context/OnlineStatusContext.jsx` | Online/offline detection |

## Cara Pakai

### Barcode Scanner
1. Klik ikon kamera di search bar POS
2. Izinkan akses kamera
3. Arahkan kamera ke barcode produk
4. Scanner otomatis mencari produk dan menutup

### PWA Install
1. Buka aplikasi di Chrome Android
2. Muncul prompt "Add to Home Screen"
3. Install — aplikasi terbuka tanpa browser chrome

### Fullscreen
1. Klik ikon fullscreen di header POS
2. Browser masuk mode fullscreen
3. Klik lagi untuk keluar
