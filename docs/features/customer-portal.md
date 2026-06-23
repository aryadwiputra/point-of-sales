# Customer Portal (Self-Service)

Kembali ke indeks dokumentasi: `docs/README.md`

## Tujuan

Pelanggan bisa melihat invoice, status pembayaran, dan membayar piutang secara online tanpa perlu login — cukup melalui link yang dibagikan.

## Fitur Saat Ini

- **Invoice detail** — lihat item, harga, diskon, PPN, total
- **Status pembayaran** — Lunas, Menunggu, Belum Lunas
- **Riwayat transaksi** — untuk customer yang sama (via token akses)
- **Bayar piutang online** — jika status `pay_later` dan belum Lunas, ada tombol "Bayar Sekarang" yang mengarah ke payment gateway
- **Token-based access** — URL unik per transaksi, tidak bisa ditebak (UUID v4)
- **Guest layout** — halaman publik, tidak perlu login
- **Share button** — di halaman print transaksi, copy link portal ke clipboard

## Keamanan

- Token akses: UUID v4 — tidak bisa ditebak
- Token hanya untuk 1 invoice (tidak bisa akses invoice lain)
- Tidak ada data sensitif yang ditampilkan
- Rate limit per IP

## Database

- `transactions.access_token` — UUID, unique
- `receivables.access_token` — UUID, unique
- Token auto-generated saat transaksi dibuat

## Halaman dan Route

| Route | Fungsi | Auth |
|-------|--------|------|
| `portal.transaction` | Lihat detail transaksi | Token-based |
| `portal.receivable.pay` | Bayar piutang via payment gateway | Token-based |

## Sharing Flow

1. Kasir selesai checkout
2. Di halaman print, klik tombol "Share"
3. Link portal otomatis tercopy ke clipboard
4. Kasir kirim link ke customer via WhatsApp
5. Customer buka link → lihat invoice → bayar jika perlu
