---
title: "Point of Sales & Operasional Retail"
slug: "point-of-sales-operasional-retail"
summary: "Aplikasi POS berbasis Laravel dan React untuk mengelola transaksi kasir, inventory, piutang, hutang, retur, shift kasir, CRM dasar, pembelian, dan laporan operasional dalam satu dashboard terintegrasi."
client_name: ""
client_website: ""
client_description: "Aplikasi ini ditujukan untuk kebutuhan operasional toko atau bisnis retail yang membutuhkan alur kasir, kontrol stok, pengelolaan pembelian, keuangan operasional, dan kontrol akses berbasis peran dalam satu sistem."
case_study_label: "Proyek Mandiri"
role: "Laravel / Full-Stack Developer"
tech_stack:
  - Laravel 12
  - PHP 8.2
  - Inertia.js 2
  - React 18
  - MySQL
  - Tailwind CSS 3
  - Vite 5
  - Spatie Laravel Permission
  - Midtrans
  - Xendit
categories:
  - laravel
  - fullstack
  - dashboard
  - integration
teaser_label: "Sistem Operasional"
featured_image: "/assets/portfolio/point-of-sales-operasional-retail/cover.png"
featured_image_alt: "Dashboard aplikasi point of sales dan operasional retail"
demo_url: ""
repo_url: ""
start_date: "2024-06-13"
end_date: "2026-05-05"
is_featured: true
sort_order: 1
status: published
sidebar_title: "Konteks Implementasi"
sidebar_description: "Project ini dibangun sebagai sistem operasional retail yang menyatukan alur kasir, inventory, pembelian, pembayaran, laporan, dan kontrol akses agar proses bisnis harian lebih rapi, aman, dan mudah dikembangkan."
---

## Latar Belakang

Project ini dikembangkan sebagai platform operasional retail yang menggabungkan alur kasir dan kebutuhan backoffice dalam satu aplikasi. Fokus utamanya bukan hanya mencatat transaksi penjualan, tetapi juga menjaga agar stok, piutang pelanggan, hutang supplier, retur, pembelian, konfigurasi pembayaran, dan laporan bisnis tetap sinkron dalam satu dashboard.

Pengguna utamanya adalah admin toko, kasir, dan tim operasional yang membutuhkan alur kerja cepat di sisi front-office, tetapi tetap memiliki kontrol data yang rapi untuk kebutuhan audit, monitoring, dan pengambilan keputusan harian.

## Tantangan Utama

Tantangan terbesar dalam project ini ada pada penggabungan banyak proses bisnis yang saling berhubungan. Satu transaksi penjualan tidak berhenti di checkout, tetapi juga memengaruhi stok, profit, dokumen cetak, status pembayaran, loyalitas pelanggan, bahkan bisa berlanjut ke piutang jika metode bayarnya ditunda.

Di sisi lain, aplikasi juga harus tetap aman untuk dipakai banyak peran dengan hak akses berbeda. Karena itu, kebutuhan authorization, audit trail, validasi shift kasir aktif, dan proteksi aksi sensitif menjadi bagian penting dari implementasi, terutama untuk modul payment settings, cashier shift, retur, pengelolaan bank account, dan konfirmasi pembayaran.

## Solusi yang Diterapkan

Solusi yang dipilih adalah membangun aplikasi full-stack berbasis Laravel, Inertia.js, dan React agar alur backend dan dashboard tetap terintegrasi tanpa memisahkan aplikasi menjadi dua codebase yang berbeda.

Secara arsitektur, routing dashboard dipusatkan di Laravel dengan middleware `auth`, permission berbasis Spatie, dan middleware tambahan seperti `active_shift`, `bot.guard`, `registration.enabled`, dan `step_up` untuk membatasi aksi tertentu sesuai konteks operasionalnya.

Modul utama dalam sistem ini meliputi:

- POS dan transaksi kasir dengan cart, hold/resume, checkout, cetak receipt, dan histori transaksi.
- Inventory dan katalog produk yang mencakup kategori, produk, pricing rules, mutasi stok, dan stock opname.
- Customer management, member management, segmentasi pelanggan, voucher pelanggan, dan automasi CRM dasar.
- Pembelian dan supplier workflow melalui purchase order, goods receiving, hutang supplier, dan supplier return.
- Pengelolaan receivable, payable, sales return, cashier shift, audit log, serta dokumen PDF operasional.
- Konfigurasi sistem seperti payment settings, bank accounts, loyalty settings, store profile, target, dan laporan penjualan maupun profit.

## Implementasi Teknis

### Backend

Backend dibangun dengan Laravel 12 dan PHP 8.2, menggunakan pola controller modular di `app/Http/Controllers/Apps/` untuk memisahkan tiap domain fitur. Logika lintas modul dipindahkan ke service layer seperti `StockMutationService`, `CashierShiftService`, `ReceivableService`, `PurchaseOrderService`, `SupplierReturnService`, `CrmAutomationService`, `CustomerSegmentationService`, `LoyaltyService`, `GoodsReceivingService`, dan `AuditLogService` agar controller tetap fokus pada orkestrasi request.

Aplikasi ini memakai RBAC berbasis `spatie/laravel-permission`, sehingga setiap route dashboard dapat diproteksi dengan permission yang spesifik. Untuk flow yang lebih sensitif, sistem menambahkan middleware khusus seperti `EnsureActiveCashierShift`, `EnsureBotGuard`, `EnsurePublicRegistrationEnabled`, `EnforceAbsoluteSessionLifetime`, dan middleware step-up confirmation pada aksi tertentu.

Di level data, aplikasi menangani domain yang cukup lengkap: transaksi, detail transaksi, profit, receivable, payable, customer credit, pricing rule, purchase order, goods receiving, sales return, supplier return, stock mutation, stock opname, bank account, payment setting, customer segment, voucher pelanggan, loyalty point history, dan audit log.

Dokumen PDF untuk invoice, receipt, shipping, receivable, dan payable dihasilkan melalui integrasi `barryvdh/laravel-dompdf`. Pengujian memanfaatkan SQLite in-memory agar test suite tetap ringan dan cepat dijalankan selama pengembangan.

### Frontend

Frontend dashboard dibangun menggunakan Inertia.js 2 dan React 18, sehingga pengalaman pengguna tetap terasa seperti SPA tetapi tetap mengikuti pola routing dan response dari Laravel. Struktur halaman dipisahkan per modul di `resources/js/Pages/Dashboard/`, misalnya untuk transaksi, produk, stock opname, receivables, payables, audit logs, cashier shifts, CRM, purchase order, goods receiving, supplier return, dan laporan.

Untuk pengalaman penggunaan, aplikasi memanfaatkan Tailwind CSS 3 dengan semantic color token, Ziggy untuk helper routing di sisi React, `react-hot-toast` dan `sweetalert2` untuk feedback interaksi, serta `chart.js` untuk visualisasi laporan. Pendekatan ini membuat dashboard cukup konsisten untuk alur operasional yang padat, terutama pada POS, histori transaksi, monitoring stok, pengelolaan pelanggan, dan halaman laporan.

### Integrasi

Project ini memiliki integrasi pembayaran dengan Midtrans dan Xendit melalui webhook API di `routes/api.php`. Sistem menyediakan `PaymentGatewayManager` beserta adapter gateway terpisah agar implementasi pembayaran tetap modular dan mudah dikembangkan untuk skenario callback maupun konfirmasi pembayaran.

Selain payment gateway, aplikasi juga mengintegrasikan data wilayah Indonesia menggunakan `laravolt/indonesia`, barcode generator untuk kebutuhan identifikasi produk atau dokumen, Ziggy untuk sinkronisasi route Laravel ke React, serta storage publik Laravel untuk file seperti gambar produk.

Integrasi penting lainnya ada pada dokumen publik dan internal: invoice publik, receipt cetak, shipping label, serta PDF finansial untuk piutang maupun hutang. Konfigurasi `APP_URL` menjadi elemen penting karena webhook pembayaran membutuhkan endpoint publik agar callback provider dapat bekerja dengan benar.

## Hasil dan Dampak

Hasil akhirnya adalah sebuah platform operasional retail yang jauh lebih terstruktur dibanding sekadar aplikasi kasir sederhana. Transaksi, stok, retur, pembelian, piutang, hutang, dan laporan bergerak dalam alur data yang lebih konsisten sehingga potensi pencatatan ganda atau koreksi manual bisa ditekan.

Beberapa dampak yang paling terasa dari implementasi ini adalah:

- proses kasir menjadi lebih cepat melalui flow cart, hold/resume, dan checkout multi-metode pembayaran
- kontrol stok menjadi lebih akurat karena setiap penyesuaian penting diarahkan ke histori mutasi dan stock opname
- proses pembelian dan penerimaan barang menjadi lebih rapi melalui purchase order dan goods receiving
- proses penagihan dan pembayaran operasional lebih tertata melalui modul receivables dan payables
- keamanan akses lebih terjaga dengan permission per modul, pembatasan shift aktif, dan proteksi aksi sensitif
- perubahan penting lebih mudah ditelusuri melalui audit log dan dokumen transaksi yang terdokumentasi
- fondasi pengembangan fitur lanjutan menjadi lebih kuat karena domain bisnis sudah dipisahkan ke modul dan service yang jelas

## Catatan Tambahan

Project ini juga menunjukkan perhatian pada maintainability dan kesiapan operasional. Dokumentasi internal sudah dipisahkan per modul, sehingga onboarding developer dan maintenance fitur menjadi lebih mudah. Dari sisi keamanan, route sensitif dilindungi dengan kombinasi auth, permission, throttle protection, session policy, dan kontrol registration publik.

Dari sisi deployment, sistem juga menandai beberapa kebutuhan environment yang penting seperti `php artisan storage:link`, migrasi terbaru, dan `APP_URL` publik untuk webhook pembayaran. Secara keseluruhan, project ini cocok diposisikan sebagai studi kasus full-stack Laravel untuk sistem operasional retail yang tidak hanya fokus pada checkout, tetapi juga pada observability, kontrol akses, integrasi pembayaran, dan konsistensi data lintas modul.

```yaml
gallery:
  - src: "/assets/portfolio/point-of-sales-operasional-retail/gallery-01.png"
    alt: "Dashboard overview aplikasi point of sales"
    label: "Dashboard utama dan ringkasan operasional"
  - src: "/assets/portfolio/point-of-sales-operasional-retail/gallery-02.png"
    alt: "Halaman transaksi kasir dengan cart dan checkout"
    label: "Modul POS, cart, dan checkout kasir"
  - src: "/assets/portfolio/point-of-sales-operasional-retail/gallery-03.png"
    alt: "Halaman inventory dan stock opname"
    label: "Inventory, stock opname, dan mutasi stok"
```

```yaml
sidebar_points:
  - title: "Tipe proyek:"
    description: "Sistem POS dan operasional retail internal"
  - title: "Periode:"
    description: "Juni 2024 — Mei 2026"
  - title: "Stack teknis:"
    description: "Laravel 12, React 18, Inertia.js 2, MySQL, Tailwind CSS, Midtrans, Xendit"
  - title: "Cakupan:"
    description: "POS, inventory, pembelian, receivables, payables, retur, shift kasir, CRM, laporan, dan payment gateway"
```
