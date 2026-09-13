# Payables & Suppliers

Kembali ke indeks dokumentasi: `docs/README.md`

## Tujuan

Menangani master supplier dan pencatatan hutang supplier beserta pelunasannya.

## Fitur Saat Ini

- CRUD supplier
- list payables
- detail payable
- pembayaran hutang supplier
- status hutang
- PDF payable
- payable dari goods receiving pada purchase order
- supplier return yang mengoreksi stok dan saldo payable

## Halaman dan Route

- `dashboard/suppliers`
- `dashboard/payables`
- `payables.show`
- `payables.pay`
- `pdf.payables.show`

## Permission

- `suppliers-access`
- `payables-access`
- `payables-pay`

## Alur User

1. admin/kasir mengelola data supplier
2. hutang supplier dicatat pada modul payable
3. pembayaran dicatat bertahap sampai lunas
4. user dapat membuka detail dan dokumen hutang

## Integrasi Data

- `suppliers`
- `payables`
- `payable_payments`
- `bank_accounts`

## Efek Bisnis Penting

- payable tidak hanya master data; status hutang menentukan visibilitas kewajiban operasional
- dokumen PDF hutang tersedia untuk kebutuhan administrasi

## Integrasi Purchasing

Purchase order dan goods receiving menjadi sumber kewajiban supplier. Goods receiving
dapat membuat atau memperbarui payable dan membawa konteks gudang. Supplier return
mengoreksi stok serta saldo kewajiban terkait sesuai alur operasionalnya.

## File Sentral

- `app/Http/Controllers/Apps/SupplierController.php`
- `app/Http/Controllers/Apps/PayableController.php`
- `resources/js/Pages/Dashboard/Suppliers`
- `resources/js/Pages/Dashboard/Payables`
