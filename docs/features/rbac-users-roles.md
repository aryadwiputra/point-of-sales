# RBAC, Users, Roles

Kembali ke indeks dokumentasi: `docs/README.md`

## Tujuan

Mengatur kontrol akses berbasis role dan permission untuk semua modul dashboard.

## Fitur Saat Ini

- user management
- role management
- permission list
- route protection dengan middleware permission
- permission map dibagikan ke frontend via Inertia

## Halaman dan Route

- `dashboard/users`
- `dashboard/roles`
- `dashboard/permissions`

## Permission Umum

Setiap modul memakai permission sendiri, contohnya:

- `transactions-access`
- `sales-returns-*`
- `stock-opnames-*`
- `cashier-shifts-*`
- `audit-logs-access`

## Alur Otorisasi

1. permission diseed di `PermissionSeeder`
2. role disusun di `RoleSeeder`
3. user admin dibuat melalui first-install setup wizard di `/setup`
4. route memakai middleware `permission:*`
5. frontend membaca map permission dari `HandleInertiaRequests`

## Catatan Super Admin

- user `super-admin` mendapat role `super-admin`
- backend memperlakukan role `super-admin` sebagai bypass permission yang konsisten untuk `can`, `canAny`, dan middleware Spatie
- `UserSeeder` hanya digunakan untuk data demo/test, bukan oleh `DatabaseSeeder`
- cache permission Spatie harus di-reset saat seeding agar permission baru terbaca konsisten
- role lama `permission-access` dinormalisasi ke `permissions-access` saat seeding agar naming RBAC tidak ambigu

## Integrasi Frontend

Frontend membaca:

- `auth.permissions`
- `auth.super`

Ini dipakai untuk menampilkan atau menyembunyikan menu dan action tertentu.

Helper frontend utama:

- `resources/js/Utils/authorization.js`
- `resources/js/Utils/Permission.jsx`

## Batasan Saat Ini

- backend tetap menjadi sumber kebenaran utama
- frontend hanya untuk gating UI, bukan keamanan final

## File Sentral

- `database/seeders/PermissionSeeder.php`
- `database/seeders/RoleSeeder.php`
- `database/seeders/UserSeeder.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `resources/js/Utils/Menu.jsx`
