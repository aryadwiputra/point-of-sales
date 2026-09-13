# Receivables

Kembali ke indeks dokumentasi: `docs/README.md`

## Tujuan

Mencatat piutang pelanggan yang berasal dari transaksi `pay_later` dan menyediakan alur pelunasan bertahap.

## Fitur Saat Ini

- list piutang
- filter status, customer, invoice, due date
- detail piutang
- pembayaran parsial
- status `unpaid`, `partial`, `paid`, `overdue`
- PDF receivable
- reminder jatuh tempo dan overdue melalui automation CRM
- pengiriman langsung melalui WhatsApp jika service aktif dan device terhubung

## Halaman dan Route

- `dashboard/receivables`
- `receivables.show`
- `receivables.pay`
- `pdf.receivables.show`

## Permission

- `receivables-access`
- `receivables-pay`

## Alur User

1. checkout `pay_later` membuat receivable
2. user memantau daftar piutang
3. user mencatat pembayaran
4. sistem memperbarui nilai `paid`, `remaining`, dan `status`

## Integrasi Data

- `receivables`
- `receivable_payments`
- `transactions`
- `customers`
- `bank_accounts`

## Efek Bisnis Penting

- pembayaran receivable ikut memengaruhi `payment_status` transaksi terkait
- sales return dapat mengoreksi total receivable jika retur berasal dari transaksi piutang

## Reminder dan Delivery

Reminder dapat dibuat untuk jatuh tempo, overdue, dan repeat order. Command scheduler
menghasilkan pekerjaan reminder secara berkala. Delivery dapat menggunakan WhatsApp
Gateway jika `wa_enabled=true` dan device sudah terhubung; jika tidak, campaign dapat
ditangani melalui link WhatsApp atau follow-up manual.

Belum ada approval flow terpisah untuk pencatatan pembayaran receivable.

## File Sentral

- `app/Http/Controllers/Apps/ReceivableController.php`
- `resources/js/Pages/Dashboard/Receivables`
