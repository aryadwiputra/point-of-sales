# Dokumentasi Point of Sales

Dokumentasi ini ditujukan untuk developer yang ingin setup, memahami alur modul, dan melakukan maintenance aplikasi.

## Daftar Isi

### Onboarding
- `docs/getting-started.md` — setup dari awal
- `docs/configuration.md` — environment, payment, tax, printer

### Arsitektur
- `docs/architecture-overview.md` — stack, middleware, service layer
- `docs/feature-index.md` — indeks semua modul

### POS & Transaksi
- `docs/features/pos-transactions.md` — cart, hold/resume, checkout multi-payment
- `docs/features/sales-returns.md` — retur penjualan, refund, store credit
- `docs/features/cashier-shifts.md` — buka/tutup shift kasir
- `docs/features/customer-portal.md` — invoice publik, bayar piutang online 🆕

### Inventory & Warehouse
- `docs/features/inventory-stock.md` — produk, stock opname, mutation
- `docs/features/multi-warehouse.md` — multi-gudang, stock transfer 🆕
- `docs/features/unit-conversion.md` — multi-satuan (pcs, box, kg) 🆕

### Purchasing & Finance
- `docs/features/purchasing-chain.md` — PO, goods receiving, supplier return 🆕
- `docs/features/payables-suppliers.md` — hutang supplier
- `docs/features/receivables.md` — piutang pelanggan
- `docs/features/tax-management.md` — PPN, NPWP, NIB 🆕

### Pricing & Loyalty
- `docs/features/promotions-loyalty.md` — pricing rules, vouchers, loyalty, price list 🆕

### CRM
- `docs/features/member-management.md` — member CRUD
- `docs/features/crm-segments.md` — segments, campaigns, reminders 🆕

### Settings & Admin
- `docs/features/settings-payments.md` — payment gateways, bank accounts, store profile, target
- `docs/features/rbac-users-roles.md` — users, roles, permissions
- `docs/features/audit-logs.md` — audit trail

### Reports & Documents
- `docs/features/reports-documents.md` — sales, profit, insights, PDF

### Tools & Integrations
- `docs/features/import-export.md` — CSV/Excel import & export 🆕
- `docs/features/mobile-pos.md` — PWA, barcode scanner kamera 🆕
- `docs/features/thermal-printer.md` — ESC/POS, WebUSB 🆕

### Planning
- `planning/feature-roadmap.md` — roadmap v2.1–v3.0
- `planning/tier-1-implementation.md` — detail Tier 1
- `planning/tier-2-implementation.md` — detail Tier 2
- `planning/tier-3-implementation.md` — detail Tier 3

## Cara Membaca Dokumentasi

1. Jika baru pertama kali: mulai dari `docs/getting-started.md`
2. Jika ingin paham struktur: baca `docs/architecture-overview.md`
3. Jika ingin kerja di modul tertentu: buka `docs/feature-index.md` → masuk ke dokumen fitur terkait
4. Jika ada masalah akses: cek `docs/features/rbac-users-roles.md`

## Catatan

- Dokumentasi mencakup seluruh fitur yang sudah ada di repo
- Planning file tersimpan di folder `planning/`
- Setiap dokumen fitur mencakup tujuan, route, permission, alur user
