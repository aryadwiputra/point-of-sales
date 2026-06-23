# PPN Tax Management

Kembali ke indeks dokumentasi: `docs/README.md`

## Tujuan

Dukungan PPN (Pajak Pertambahan Nilai) pada transaksi, dengan mode exclusive/inclusive per produk, NPWP/NIB toko, dan tarif default yang bisa dikonfigurasi.

## Definisi

| Istilah | Arti |
|---------|------|
| Tax Exclusive | Harga produk **belum** termasuk PPN. PPN ditambahkan ke grand total |
| Tax Inclusive | Harga produk **sudah** termasuk PPN. PPN dipisah untuk reporting |
| NPWP | Nomor Pokok Wajib Pajak — identitas pajak toko |
| PPN | Pajak Pertambahan Nilai (default 11%) |

## Fitur Saat Ini

- PPN per produk (exclusive/inclusive), tarif bisa berbeda per produk
- Default tarif PPN bisa diatur di Settings → Profil Toko (default 11.00%)
- Tax calculation di checkout: otomatis menambah PPN ke grand total
- Baris PPN tampil di: checkout preview, print invoice, PDF invoice, PDF receipt 80mm & 58mm, thermal receipt
- Grand total sudah termasuk PPN
- NPWP dan NIB toko di Settings → Profil Toko
- Laporan — PPN sudah termasuk di grand total

## Database

### Products
- `tax_type` — `exclusive` atau `inclusive`
- `tax_rate` — persentase (decimal 5,2), default 11.00

### Transactions
- `tax_rate` — tarif yang dipakai (nullable)
- `tax_total` — total PPN dalam rupiah
- `customer_npwp` — NPWP customer (opsional)

### Settings
- `store_npwp` — NPWP toko
- `store_nib` — NIB toko
- `tax_default_rate` — tarif default untuk produk baru

## Halaman dan Route

| Route | Fungsi |
|-------|--------|
| `settings.store` | Atur NPWP, NIB, tarif PPN default |

## Alur Perhitungan

### Exclusive (default)
```
Harga produk: Rp 10.000
PPN 11%:      Rp  1.100
Total:        Rp 11.100
```

### Inclusive
```
Harga produk: Rp 11.100 (sudah include PPN)
PPN 11%:      Rp  1.100 (dihitung: 11100 - (11100 / 1.11))
Total:        Rp 10.000 + Rp 1.100
```

## Catatan

- Tax hanya mempengaruhi grand_total, tidak mempengaruhi diskon/voucher/loyalty
- Shipping cost juga dikenakan PPN dengan rate yang sama
- Jika `tax_rate = 0`, PPN tidak dihitung
- Setting NPWP/NIB tidak wajib — bisa dikosongkan
