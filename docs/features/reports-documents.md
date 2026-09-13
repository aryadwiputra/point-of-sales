# Reports & Documents

Kembali ke indeks dokumentasi: `docs/README.md`

## Tujuan

Menyediakan visibilitas operasional melalui laporan dan dokumen siap cetak / share.

## Fitur Saat Ini

- laporan penjualan
- laporan profit
- Advanced Sales Insights
- invoice transaksi publik dan internal
- receipt thermal 58mm / 80mm
- shipping label
- PDF receivable
- PDF payable

## Halaman dan Route

- `dashboard/reports/sales`
- `dashboard/reports/profits`
- `dashboard/reports/insights`
- `pdf.transactions.invoice`
- `pdf.transactions.receipt`
- `pdf.transactions.shipping`
- `pdf.receivables.show`
- `pdf.payables.show`

## Permission

- `reports-access`
- `profits-access`
- `reports-insights-access`
- akses dokumen mengikuti modul asal seperti `transactions-access`, `receivables-access`, dan `payables-access`

## Alur User

1. user membuka laporan sales atau profit
2. user memfilter data
3. user membuka dokumen transaksi atau finansial terkait
4. dokumen bisa dipakai untuk print/share

## Integrasi Data

- `transactions`
- `transaction_details`
- `profits`
- `receivables`
- `payables`
- `settings` untuk identitas toko

## Advanced Sales Insights

Dashboard insights menyediakan metrik operasional seperti penjualan per jam,
performa kasir, repeat customer, dan ringkasan tren yang membantu pemilik mengambil
keputusan berbasis data.

## Efek Bisnis Penting

- laporan sales dan profit bergantung pada kualitas data transaksi
- sales return dan inventory correction dapat memengaruhi pembacaan operasional pada laporan terkait

## Batasan Saat Ini

- cakupan metrik mengikuti data transaksi, customer, dan shift yang tersedia
- analitik tidak menggantikan laporan akuntansi atau forecasting penuh

## File Sentral

- `app/Http/Controllers/Reports/SalesReportController.php`
- `app/Http/Controllers/Reports/ProfitReportController.php`
- `app/Http/Controllers/DocumentController.php`
