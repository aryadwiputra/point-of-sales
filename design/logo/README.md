# Dikasir — Logo Kit

Arah terpilih: **Struk d** — huruf `d` yang dibentuk dari struk kasir (mangkuk bulat + batang dengan sobekan bergerigi).

## Struktur

```
design/logo/
├── brand-guidelines.md        panduan pemakaian (1 halaman)
├── board-spec.json            spesifikasi presentation board
├── dikasir-presentation.html  board interaktif
├── slides/                    board versi PNG per slide
├── src/                       master SVG (bisa diedit)
│   ├── dikasir-symbol.svg              hitam, master
│   ├── dikasir-symbol-small.svg        potongan ≤24 px (batang rata, mangkuk lebih tebal)
│   ├── dikasir-symbol-two-tone.svg     indigo + cyan (aksen sobekan)
│   ├── dikasir-lockup-horizontal.svg   simbol + wordmark
│   ├── dikasir-lockup-stacked.svg
│   └── dikasir-wordmark.svg            wordmark ter-outline (Inter Bold)
└── variants/                  ekspor siap pakai
    ├── dikasir-symbol-{black,white,mono-4f46e5,square,favicon,app-icon}.svg
    ├── dikasir-horizontal-{black,white,mono-4f46e5}.svg (+ PNG 1200)
    ├── favicon.ico, favicon-{16,32,48}.png, favicon.svg
    ├── apple-touch-icon.png, icon-192.png, icon-512.png, maskable-512.png
    └── site.webmanifest, head-snippet.html
```

## Geometri

- Kanvas 256 × 256; mangkuk ring Ø116 (outer r58 / counter r26), batang 36 × 164 di kanan, sobekan 3 gigi (12 unit).
- Huruf `d` menempati bbox 132 × 164 — footprint sedikit lebih tinggi dari lebar, sesuai proporsi huruf.
- Ketebalan minimum pada 16 px: ring 2,0 px, batang 2,25 px. Potongan small-size menebalkan ring (inner r24) dan meratakan batang.
- Wordmark Inter Bold di-outline (tanpa live text), tracking −1,9% pada 100 px.

## Tes yang dijalankan

- `svg_audit.py`: skor 97–99/100 per file; satu-satunya warning adalah *near-miss angle* pada glyph `k` Inter (bawaan typeface, bukan geometri logo).
- Uji ukuran: favicon memakai potongan small-size; ketebalan diverifikasi ≥ 1,5 px pada 16 px.
- Satu warna & reversed: `-black`, `-white`, `-mono` disertakan; two-tone tetap terbaca saat dijadikan grayscale (bentuk tidak bergantung warna).

## Catatan jujur

- Logo belum dicek trademark; untuk dipakai komersial serius, lakukan pencarian merek (basis data DJKI/EUIPO/USPTO + reverse image search).
- Inter berlisensi SIL OFL 1.1 — boleh dipakai di logo; wordmark sudah di-outline sehingga tidak bergantung font saat render.
- Belum dipasang ke aplikasi. Langkah integrasi (butuh persetujuan): ganti `public/images/icon.svg`, `public/favicon.ico`, `public/images/icon-192.png` & `icon-512.png`, `theme_color` di `public/manifest.json`, dan `og-image.svg`.

## Langkah berikut

1. Review visual (buka `dikasir-presentation.html` atau `slides/`).
2. Integrasi ke aset aplikasi (favicon, PWA, og-image) — saya bisa kerjakan.
3. Opsional: wordmark kustom untuk ownership lebih kuat.