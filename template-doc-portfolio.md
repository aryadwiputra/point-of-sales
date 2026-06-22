# Portfolio Template

Gunakan file ini sebagai template sumber konten untuk 1 portfolio/project.

## Cara pakai

- 1 file Markdown = 1 project
- Isi bagian `Frontmatter` untuk metadata utama
- Isi bagian `Content` untuk deskripsi panjang project
- Bagian `Gallery` dipakai untuk daftar screenshot/preview
- Bagian `Sidebar Points` dipakai untuk poin konteks implementasi yang ingin ditampilkan di sidebar

---

## Frontmatter

```yaml
title: "Point of Sales"
slug: "point-of-sales"
summary: "Aplikasi point of sales berbasis Laravel, Inertia, dan React untuk operasional kasir, inventory, piutang, hutang, retur, shift kasir, dan audit log dalam satu dashboard terpadu."
client_name: ""
client_website: ""
client_description: ""
case_study_label: "Proyek Mandiri"
role: "Laravel / Full-Stack Developer"
tech_stack:
    - Laravel 12
    - PHP 8.2
    - Inertia.js 2
    - React 18
    - MySQL
    - Tailwind CSS 3
    - Spatie Laravel Permission
    - Midtrans
    - Xendit
categories:
    - laravel
    - fullstack
    - dashboard
    - integration
teaser_label: "Sistem Operasional Toko"
featured_image: "/assets/portfolio/point-of-sales/cover.png"
featured_image_alt: "Dashboard aplikasi point of sales dengan modul operasional toko"
demo_url: ""
repo_url: "https://github.com/aryadwiputra/point-of-sales.git"
start_date: "2024-06-13"
end_date: "2026-05-05"
is_featured: true
sort_order: 1
status: published
sidebar_title: "Konteks Implementasi"
sidebar_description: "Project ini dirancang sebagai sistem operasional toko yang tidak berhenti di kasir, tetapi juga mencakup inventory, keuangan, kontrol akses, dan observability agar tetap nyaman dipelihara saat modul bertambah."
```

### Catatan field

- `title` — judul project
- `slug` — URL portfolio, contoh: `sistem-manajemen-aset`
- `summary` — ringkasan pendek untuk card/listing
- `client_name` — nama klien, boleh kosong kalau project mandiri
- `client_website` — website klien, opsional
- `client_description` — deskripsi singkat konteks klien / bisnis
- `case_study_label` — contoh: `Proyek Klien` atau `Proyek Mandiri`
- `role` — peran kamu di project
- `tech_stack` — daftar stack utama
- `categories` — gunakan kombinasi dari:
    - `laravel`
    - `fullstack`
    - `dashboard`
    - `integration`
- `teaser_label` — label kecil di card, contoh: `Sistem Operasional`
- `featured_image` — gambar cover utama
- `featured_image_alt` — alt text cover utama
- `demo_url` — link demo/live, opsional
- `repo_url` — link repository, opsional
- `start_date` / `end_date` — format `YYYY-MM-DD`
- `is_featured` — `true` atau `false`
- `sort_order` — urutan tampil
- `status` — untuk sekarang gunakan `published`
- `sidebar_title` / `sidebar_description` — isi panel kanan detail project

---

## Content

> Bagian ini akan menjadi isi utama `detail.content`.
> Boleh pakai heading Markdown, bullet list, dan paragraf biasa.

## Latar Belakang

Project ini dibangun sebagai aplikasi point of sales modern untuk kebutuhan operasional toko yang lebih lengkap daripada sistem kasir biasa. Fokusnya bukan hanya mencatat transaksi checkout, tetapi juga menghubungkan proses penjualan dengan pengelolaan customer, inventory, piutang, hutang supplier, retur penjualan, shift kasir, dan audit aktivitas dalam satu dashboard yang konsisten.

Pengguna utamanya adalah admin dan kasir. Admin membutuhkan kontrol terhadap data master, laporan, pengaturan pembayaran, user management, dan observability. Kasir membutuhkan alur transaksi yang cepat, minim friksi, dan aman untuk operasional harian di meja kasir.

## Tantangan Utama

Tantangan utama project ini adalah menyatukan banyak proses bisnis toko dalam satu aplikasi tanpa membuat alur kerja menjadi rumit. Modul transaksi harus tetap cepat untuk kasir, tetapi di saat yang sama data yang dihasilkan harus langsung terhubung ke modul lain seperti receivables, profit, stock mutation, sales return, dan dokumen cetak.

Di sisi teknis, project ini juga menghadapi kebutuhan otorisasi yang cukup granular karena hampir setiap area dashboard memiliki permission tersendiri. Selain itu, integrasi pembayaran eksternal melalui Midtrans dan Xendit menambah kebutuhan validasi webhook, pengaturan payment gateway, dan alur settlement yang tetap sinkron dengan transaksi internal.

## Solusi yang Diterapkan

Solusi yang dipilih adalah membangun arsitektur modular berbasis Laravel di backend dan Inertia.js + React di frontend, sehingga halaman dashboard tetap terasa seperti SPA tanpa memisahkan codebase frontend dan backend secara ekstrem.

Beberapa pendekatan implementasi yang menjadi fondasi project ini:

- route dashboard dipisahkan dan diproteksi dengan `auth`, `verified`, serta permission middleware berbasis Spatie Laravel Permission
- logika lintas modul ditempatkan ke layer service seperti `CashierShiftService`, `StockMutationService`, `ReceivableService`, `PurchaseOrderService`, `PricingService`, dan integrasi payment gateway
- React page dipisahkan per modul di area dashboard sehingga alur pengembangan dan maintenance lebih terstruktur
- shared props Inertia dipakai untuk menyebarkan informasi auth, permission, notifikasi, dan profil toko ke seluruh halaman
- transaksi dijadikan pusat relasi agar perubahan pada checkout, retur, piutang, dan dokumen tetap konsisten

## Implementasi Teknis

### Backend

Backend dibangun dengan Laravel 12 dan memanfaatkan pola controller + service untuk menjaga controller tetap fokus pada request lifecycle. Area `app/Http/Controllers/Apps` menangani modul dashboard, sementara `app/Services` menampung logika yang dipakai lintas flow bisnis seperti audit log, stock mutation, cashier shift, receivable, payable aging, pricing rule, loyalty, goods receiving, purchase order, sampai CRM automation.

Aplikasi memakai RBAC berbasis Spatie Laravel Permission. Hampir seluruh route dashboard dilindungi permission spesifik seperti akses dashboard, transaksi, laporan, settings, role, dan permission management. Untuk operasi transaksi POS, middleware `active_shift` memastikan kasir hanya bisa melakukan aksi penting ketika memiliki shift aktif. Beberapa aksi sensitif juga sudah dipisahkan untuk kebutuhan step-up authentication.

Dari sisi data, project ini mencakup model operasional yang cukup lengkap: transaksi dan detail transaksi, produk, customer, supplier, receivable, payable, sales return, stock opname, stock mutation, cashier shift, bank account, payment setting, audit log, sampai modul CRM seperti segment, voucher, dan campaign. Untuk testing, project dikonfigurasi menggunakan SQLite in-memory agar pengujian lebih cepat dan terisolasi.

### Frontend

Frontend menggunakan Inertia.js 2 dan React 18 dengan struktur halaman di `resources/js/Pages/Dashboard`. Pendekatan ini membuat developer bisa membangun pengalaman dashboard yang responsif sambil tetap memanfaatkan routing, validation, dan server-side flow dari Laravel.

UI dibangun dengan Tailwind CSS 3 dan komponen dashboard reusable seperti table, modal, widget, pagination, input, listbox, dan sidebar. Pada modul POS, alur kasir dipecah ke komponen yang jelas seperti pencarian produk, grid produk, cart panel, payment panel, held transactions, customer select, dan dukungan barcode scanner. Pendekatan ini membantu menjaga pengalaman transaksi tetap cepat walaupun fitur operasionalnya cukup banyak.

### Integrasi

Project ini terintegrasi dengan Midtrans dan Xendit untuk payment workflow. Webhook publik disediakan melalui `routes/api.php` agar status pembayaran dari gateway dapat diproses tanpa login. Selain itu, sistem juga mendukung transfer bank manual melalui bank account management dan payment settings.

Untuk dokumen operasional, aplikasi menyediakan invoice publik, receipt, shipping label, PDF piutang, dan PDF hutang. Data wilayah Indonesia ditangani dengan paket Laravolt Indonesia agar form customer lebih relevan dengan kebutuhan lokal. Di sisi observability, audit log dipakai untuk melacak perubahan penting pada modul sensitif.

## Hasil dan Dampak

Hasil dari implementasi ini adalah sebuah sistem POS yang mencakup alur operasional toko secara end-to-end, mulai dari transaksi kasir sampai kontrol inventory, keuangan, dan pelaporan. Aplikasi tidak berhenti pada checkout, tetapi juga menyediakan jalur lanjutan untuk retur, piutang, hutang supplier, dokumen, dan monitoring aktivitas pengguna.

Dampak utamanya bersifat struktural dan operasional:

- proses kasir lebih cepat karena alur POS dipisahkan secara fokus dan mendukung barcode, hold/resume, serta multi-metode pembayaran
- data transaksi lebih bernilai karena langsung mengalir ke modul inventory, receivables, profit, dan dokumen
- kontrol akses lebih aman dan lebih mudah dikelola karena permission dibuat granular per modul dan per aksi
- maintenance lebih nyaman karena dokumentasi modul, struktur service, dan pemisahan page dashboard sudah cukup rapi untuk dilanjutkan developer lain

## Catatan Tambahan

Project ini juga menunjukkan perhatian pada area keamanan dan maintainability. Repo sudah memiliki dokumentasi arsitektur, dokumentasi fitur per modul, serta roadmap peningkatan keamanan yang mencakup hardening auth, throttling, webhook hygiene, session policy, security headers, dan audit trail yang lebih kuat.

Di level operasional, ada beberapa perhatian penting yang sudah terdokumentasi, seperti kebutuhan `APP_URL` publik untuk webhook, `storage:link` untuk gambar produk, migrasi tambahan untuk modul baru, dan refresh permission cache setelah seeding. Kombinasi dokumentasi, service layer, dan pembagian modul ini membuat project cukup siap untuk dikembangkan lebih lanjut.

---

## Gallery

Gunakan list YAML ini untuk screenshot tambahan.

```yaml
gallery:
    - src: "/assets/portfolio/point-of-sales/gallery-01.png"
      alt: "Dashboard utama aplikasi point of sales"
      label: "Dashboard operasional"
    - src: "/assets/portfolio/point-of-sales/gallery-02.png"
      alt: "Halaman point of sales untuk transaksi kasir"
      label: "POS dan checkout"
    - src: "/assets/portfolio/point-of-sales/gallery-03.png"
      alt: "Dokumen invoice transaksi"
      label: "Invoice dan dokumen bisnis"
```

---

## Sidebar Points

Gunakan list YAML ini untuk poin sidebar detail project.

```yaml
sidebar_points:
    - title: "Jenis proyek:"
      description: "Aplikasi point of sales dan sistem operasional toko"
    - title: "Periode:"
      description: "Juni 2024 — Mei 2026"
    - title: "Stack teknis:"
      description: "Laravel 12, Inertia.js, React 18, MySQL, Tailwind CSS"
    - title: "Fokus implementasi:"
      description: "POS, inventory, receivables, payables, sales return, cashier shift, audit log"
```

---

## Contoh singkat

```yaml
title: "Sistem Manajemen Gudang"
slug: "sistem-manajemen-gudang"
summary: "Platform internal untuk mengelola stok, approval, dan pelaporan gudang multi-lokasi."
client_name: "PT Contoh Distribusi"
role: "Laravel / Full-Stack Developer"
tech_stack:
    - Laravel
    - MySQL
    - Tailwind CSS
categories:
    - laravel
    - dashboard
    - integration
featured_image: "/assets/portfolio/sistem-manajemen-gudang/cover.png"
featured_image_alt: "Tampilan dashboard sistem manajemen gudang"
start_date: "2025-01-01"
end_date: "2025-04-15"
is_featured: true
sort_order: 2
status: published
```
