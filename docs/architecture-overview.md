# Architecture Overview

Kembali ke indeks dokumentasi: `docs/README.md`

## Stack

- **Backend:** Laravel 12 (PHP 8.2+)
- **Frontend:** Inertia.js 2.0 + React 18, Vite 5
- **Styling:** Tailwind CSS 3 (custom theme in `tailwind.config.js`)
- **Auth/RBAC:** Spatie Laravel Permission + Laravel Breeze
- **DB:** MySQL (default); SQLite in-memory untuk testing
- **Payment Gateways:** Midtrans, Xendit

## Struktur Area Penting

- `routes/web.php` — ~60+ route dashboard, public share, portal
- `routes/api.php` — webhook Midtrans & Xendit (tanpa auth)
- `app/Http/Controllers/Apps/` — controller per modul dashboard
- `app/Http/Controllers/Reports/` — controller laporan
- `app/Http/Controllers/DocumentController.php` — PDF documents
- `app/Http/Controllers/PublicPortalController.php` — customer self-service
- `app/Http/Middleware/` — 7 custom middleware
- `app/Http/Middleware/HandleInertiaRequests.php` — shared props global (auth, permissions, notifications, shift, store profile, security)
- `app/Models/` — ~45+ model
- `app/Services/` — business logic layer
- `resources/js/Pages/Dashboard/` — Inertia page components
- `resources/js/Pages/Public/` — public Inertia pages (customer portal)
- `resources/js/Layouts/` — 4 layout: POSLayout, DashboardLayout, AuthenticatedLayout, GuestLayout
- `database/migrations/` — ~55+ migration
- `database/seeders/` — 8 seeder (executed in chain order)

## Alur Request Umum

1. Route dashboard diproteksi `auth` + `verified` + `permission`
2. Controller menyiapkan data dari Model/Service
3. Inertia merender page React di `resources/js/Pages/Dashboard/**/*.jsx`
4. Permission user dishare ke frontend via `HandleInertiaRequests.php`
5. Frontend menggunakan permission untuk visibility tombol/menu

## Middleware

| Alias | Class | Fungsi |
|-------|-------|--------|
| `permission` | Spatie PermissionMiddleware | Proteksi route berbasis permission string |
| `active_shift` | EnsureActiveCashierShift | Wajibkan shift aktif untuk operasi POS (cart, hold, checkout) |
| `step_up` | EnsureRecentPasswordConfirmation | Minta konfirmasi password untuk aksi sensitif (role/user CRUD, payment settings, bank accounts, payment confirmation) |
| `bot.guard` | EnsureBotGuard | Honeypot + timer anti-bot di form login/register/forgot-password |
| `registration.enabled` | EnsurePublicRegistrationEnabled | Matikan registrasi publik (default: off) |
| `SecureHeaders` | — | Security response headers |
| `EnforceAbsoluteSessionLifetime` | — | Paksa logout setelah session lifetime habis |

## Service Layer

| Service | Fungsi |
|---------|--------|
| `AuditLogService` | Catat perubahan penting dengan before/after snapshot |
| `CashierShiftService` | Lifecycle shift: open, close, force-close, summary |
| `StockMutationService` | Catat semua perubahan stok dengan audit trail |
| `PricingService` | Engine promo: qty break, bundle, buy-x-get-y |
| `LoyaltyService` | Poin, tier, voucher — earn/redeem |
| `TaxService` | Hitung PPN exclusive/inclusive per item |
| `UnitConversionService` | Konversi antar satuan (pcs ↔ box ↔ kg) |
| `BatchService` | Alokasi FEFO batch, expiring alerts |
| `ReorderService` | Produk perlu restock, buat draft PO |
| `PriceListService` | Harga khusus per kelompok pelanggan |
| `StockTransferService` | Lifecycle transfer stok antar gudang |
| `ThermalPrintService` | Generate teks receipt ESC/POS |
| `CrmAutomationService` | Campaign, reminder, automation |
| `CustomerSegmentationService` | Auto/manual segmentasi pelanggan |
| `PurchaseOrderService` | Lifecycle PO: draft, place, cancel |
| `GoodsReceivingService` | Terima barang, update stok, buat payable |
| `SupplierReturnService` | Retur ke supplier, koreksi stok + payable |
| `ReceivableService` | Aging, statement, collection stats |
| `PayableAgingService` | Aging hutang supplier |
| `PaymentGatewayManager` | Dispatch ke Midtrans/Xendit |
| `WhatsAppService` | HTTP wrapper ke Node.js whatsapp-web.js service |

## Pola Integrasi Modul

- **Transaction** adalah pusat: details, profits, receivable, sales returns, campaign logs, discount approvals
- **Product** adalah pusat inventory: stock opname, stock mutation, batch, composite, pricing rules, price list items, units
- **Warehouse** adalah dimensi baru: hampir semua tabel stok & transaksi punya `warehouse_id`
- **Audit Log** lintas modul: setiap perubahan penting dicatat via `AuditLogService`

## Alur Data Multi-Warehouse

```
Cashier buka shift → pilih warehouse
    ↓
POS cek stok di product_warehouse (product_id + warehouse_id)
    ↓
Checkout → decrement stok di product_warehouse
         → transaction.warehouse_id = shift.warehouse_id
    ↓
PO → warehouse_id
GR → inherit warehouse dari PO, increment stok di pivot
Stock Transfer → source → send → receive → destination
Stock Opname → pilih warehouse, baca stok dari pivot
```

## WhatsApp Gateway Architecture

```
┌─────────────────────────┐     HTTP      ┌──────────────────────┐
│  Laravel App            │  ──────────→  │  whatsapp-service    │
│                         │  ←──────────  │  (Node.js :3001)     │
│  WhatsAppService.php    │               │                      │
│  CrmAutomationService   │               │  whatsapp-web.js     │
│  SettingController      │               │  Puppeteer/Chrome    │
└─────────────────────────┘               └──────────┬───────────┘
                                                     │
                                              WhatsApp Web
```

- `whatsapp-service/` adalah Node.js Express server yang menjalankan `whatsapp-web.js`
- Laravel komunikasi via HTTP ke service tersebut
- Session WhatsApp disimpan di `whatsapp-service/session/` (persistent)
- Butuh Node.js + Chrome di server (Puppeteer internal)

## Pola Dokumentasi Fitur

Setiap dokumen fitur di `docs/features/` mencakup:

- tujuan modul
- fitur yang tersedia
- halaman dan route
- permission yang dibutuhkan
- alur user
- integrasi data
- catatan teknis/batasan
