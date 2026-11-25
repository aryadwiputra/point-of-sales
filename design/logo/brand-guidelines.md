# Dikasir — Logo Guidelines (ringkas)

> Satu halaman untuk tim & kontributor. Versi final: **Struk d**.

## 1. Logo

- **Idea**: huruf **d** dari nama Dikasir, dibentuk dari **struk kasir** — mangkuk bulat + batang dengan sobekan bergerigi.
- **Versi**: horizontal (utama) · stacked · symbol-only · wordmark-only · two-tone (indigo + cyan)
- **File**: `src/` (master SVG) · `variants/` (hitam, putih, mono indigo, square, favicon, app icon) · `variants/` juga berisi PNG & set ikon web/PWA

## 2. Clear space

Ruang kosong minimal **1 × ketebalan mangkuk huruf d** (≈ 12% tinggi simbol) di semua sisi. Zona ikut skala logo — jangan pakai jarak tetap.

## 3. Ukuran minimum

| Versi | Layar | Cetak |
|---|---|---|
| Horizontal | 96 px lebar | 25 mm lebar |
| Symbol | 16 px — **pakai `dikasir-symbol-favicon.svg` / potongan small-size** | 6 mm |

Di bawah 24 px selalu pakai potongan small-size (batang rata, mangkuk lebih tebal) — gerigi sobekan dihilangkan karena menjadi tekstur kabur.

## 4. Warna

| Nama | HEX | RGB | CMYK | Pantone (±) |
|---|---|---|---|---|
| Indigo (utama) | `#4F46E5` | 79 70 229 | 65 69 0 10 | 2726 C |
| Cyan (aksen) | `#06B6D4` | 6 182 212 | 72 0 14 0 | 3125 C |
| Ink (netral) | `#111827` | 17 24 39 | 75 66 55 68 | Black 6 C |

**Pasangan resmi**: indigo penuh di atas putih · putih di atas indigo · hitam di atas putih · two-tone (indigo + cyan) hanya di latar terang.
Di atas foto: pakai versi putih/hitam di area tenang, atau taruh logo dalam kontainer (tile rounded).

## 5. Tipografi

- Wordmark: **Inter Bold**, sudah di-outline jadi path (`src/dikasir-wordmark.svg`, lisensi SIL OFL 1.1 — aman untuk logo).
- UI/aplikasi: Inter (sudah dibundel di `public/inter/`), fallback `system-ui, -apple-system, Segoe UI, sans-serif`.
- Wordmark kustom (opsional) bisa dibuat nanti; untuk sekarang Inter Bold dengan tracking −1,9% adalah master yang dipakai.

## 6. Jangan

Jangan regangkan/memipihkan · jangan ganti warna di luar palet · jangan rotasi · jangan tambah shadow, outline, gradient, atau efek · jangan ubah susunan/ukuran bagian lockup · jangan taruh di latar ramai tanpa kontainer · jangan mengetik ulang wordmark dengan font lain.

## 7. Kontak

Pertanyaan pemakaian & master file: folder `design/logo/` di repo. Ikuti aturan kontribusi repo untuk perubahan aset brand.