# AGENTS.md — Point of Sales

Open-source POS system (200+ stars). Laravel 12 + Inertia 2.0 + React 18.

## Important: This Repo

**Remote:** `git@github.com:aryadwiputra/point-of-sales.git`

**Branch structure:**
- `main` — production. Protected. PR only from `development`.
- `development` — integration branch. Feature branches merge here via PR.
- `release/*` — release candidates. Created from `development`, merged to `main` + tagged.
- `revamp-frontend` — legacy UI overhaul branch (inactive).
- `feature/*` — individual feature work. Branch from `development`, PR to `development`.
- `fix/*` — hotfixes. Branch from `main`, PR to `main` + `development`.

**Tags follow semver:** `v1.0.0`, `v1.1.0`, etc.

## Stack

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Inertia.js 2.0 + React 18, Vite 5
- **Styling**: Tailwind CSS 3 (custom theme in `tailwind.config.js`)
- **Auth/RBAC**: Spatie Laravel Permission + Laravel Breeze
- **DB**: MySQL (default); SQLite in-memory for tests
- **Payment gateways**: Midtrans, Xendit (webhooks in `routes/api.php`)

## Developer Commands

```bash
# Initial setup
cp .env.example .env
composer install && npm install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link

# Dev servers — run BOTH
npm run dev          # Vite HMR
php artisan serve    # Laravel

# Testing
php artisan test                     # all
php artisan test --filter=FooTest    # one class
php artisan test --filter=test_name  # one method

# Formatting
vendor/bin/pint

# Production build
npm run build
```

## Architecture

- **Controllers**: `app/Http/Controllers/Apps/` — per-module controllers
- **Services**: `app/Services/` — business logic: AuditLog, CashierShift, StockMutation, Payments/, PricingService, CrmAutomationService, etc.
- **Layouts**: `POSLayout.jsx` (POS), `DashboardLayout.jsx` (admin), `AuthenticatedLayout.jsx` (profile), `GuestLayout.jsx` (auth)
- **Routes**: `routes/web.php` (~50+ dashboard routes), `routes/api.php` (webhooks), `routes/auth.php` (Breeze)
- **Inertia shared props**: `HandleInertiaRequests.php` — auth, permissions, notifications (low stock, receivables, payables aging), active shift, store profile

## Middleware

| Alias | Class | Applied to |
|-------|-------|------------|
| `permission` | Spatie PermissionMiddleware | Every dashboard route |
| `step_up` | EnsureRecentPasswordConfirmation | Sensitive create/update/delete: roles, users, payment settings, bank accounts, payment confirm |
| `active_shift` | EnsureActiveCashierShift | All POS transaction actions (cart CRUD, hold/resume, checkout) |
| `bot.guard` | EnsureBotGuard | Login/register/forgot-password (honeypot + timer) |
| `registration.enabled` | EnsurePublicRegistrationEnabled | Register route (default: off) |

## Seeder Chain

`DatabaseSeeder` runs in exact order with permission cache reset before & after:

```
PermissionSeeder → RoleSeeder → UserSeeder → PaymentSettingSeeder → SampleDataSeeder → OperationalCoreSeeder → FeatureCoverageSeeder
```

**Default users:** `arya@gmail.com` / `password` (admin), `cashier@gmail.com` / `password` (cashier)

## Critical Gotchas

1. **Permission cache stale after seed** — logout + login again. Seeder resets cache but session still holds old permissions.
2. **Webhooks need public APP_URL** — Midtrans/Xendit won't work with localhost.
3. **Product images need storage:link** — `php artisan storage:link` or images won't render.
4. **Missing migrations cause 500 on new modules** — run `php artisan migrate` for newer modules (purchase orders, goods receiving, supplier returns, stock opname, etc.).
5. **Tests force SQLite in-memory** — `phpunit.xml` sets `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`. Don't assume MySQL features.
6. **Both dev servers required** — Vite serves JS/CSS via HMR. `php artisan serve` alone won't work.

## Release Process

1. `development` accumulates features → branch `release/X.Y.Z`
2. QA/fix on `release/X.Y.Z` → merge to `main`
3. Tag: `git tag -a vX.Y.Z -m "vX.Y.Z"` on `main`
4. Merge `release/X.Y.Z` back to `development`
5. GitHub Release created from tag

## Frontend

- **Icons**: `@tabler/icons-react`
- **Alerts/confirm**: `react-hot-toast` + `sweetalert2`
- **Charts**: `chart.js`
- **Routing**: Ziggy `route()` helper available
- **Tailwind tokens**: `primary` (indigo), `accent` (cyan), `success` (emerald), `warning` (amber), `danger` (rose)

## Docs

- Modules: `docs/features/`
- Architecture: `docs/architecture-overview.md`
- Config: `docs/configuration.md`
- Planning: `planning/improvement-planning.md`
