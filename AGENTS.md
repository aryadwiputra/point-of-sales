# AGENTS.md — Point of Sales

Open-source POS system (200+ stars). Laravel 13 + Inertia 3.0 + React 19.

## Important: This Repo

**Remote:** `git@github.com:aryadwiputra/point-of-sales.git`

**Branch structure:**
- `main` — production. Protected. PR only from `development`.
- `development` — integration branch. Feature branches merge here via PR.
- `release/*` — release candidates. Created from `development`, merged to `main` + tagged.
- `revamp-frontend` — legacy UI overhaul branch (inactive).
- `feature/*` — individual feature work. Branch from `development`, PR to `development`.
- `fix/*` — hotfixes. Branch from `main`, PR to `main` + `development`.

**Tags follow semver:** `v1.0.0`, `v2.1.0`, etc.

## Stack

- **Backend**: Laravel 13 (composer.json requires PHP ^8.3; CI tests on PHP 8.4)
- **Frontend**: Inertia.js 3.0 + React 19, Vite 5
- **CI**: `.github/workflows/deploy.yml` uses PHP 8.4 + Node 22 for build, Node 24.15 on deploy VPS
- **Styling**: Tailwind CSS 3 (custom theme in `tailwind.config.js`)
- **Auth/RBAC**: Spatie Laravel Permission + Laravel Breeze
- **REST API**: Sanctum token-based at `/api/v1`; Scramble docs at `/docs/api`, spec at `/docs/api.json`; protect with `SCRAMBLE_DOCS_TOKEN`
- **DB**: MySQL (default); SQLite in-memory for tests
- **i18n**: react-i18next; locales in `resources/js/i18n/locales`; `SetLocale` middleware on web group
- **Payment gateways**: Midtrans, Xendit (webhooks in `routes/api.php`)
- **WhatsApp**: whatsapp-web.js via separate Node service (`whatsapp-service/`, port 3001)

## CI / Deploy

- **CI is build-only** — `.github/workflows/deploy.yml` validates composer + `npm run build` (PHP 8.4, Node 22). It does **NOT run tests**. Run `php artisan test` locally before every PR.
- **Push to `main` auto-deploys to production** (`dikasir.web.id` via SSH). Never push directly to `main` — use the release process below.
- Deploy VPS uses Node 24.15 + PHP 8.4 (`php8.4 artisan migrate --force`).
- npm is the package manager of record (`package-lock.json` committed, `bun.lock` gitignored). CI/deploy run `npm ci`. Don't switch to bun/yarn lockfiles.

## Developer Commands

```bash
# Initial setup
cp .env.example .env
composer install && PUPPETEER_SKIP_DOWNLOAD=true npm install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
# After the server starts, open the root URL; first install automatically redirects to /setup.

# Dev — runs server, queue, logs (pail), and vite in one command
composer run dev     # equivalent to `php artisan dev` (Laravel 13 DevCommand)

# Testing
php artisan test                     # all
php artisan test --filter=FooTest    # one class
php artisan test --filter=test_name  # one method

# WhatsApp Service (separate terminal)
cd whatsapp-service
npm install && npm start             # port 3001

# PM2 for production
pm2 start whatsapp-service/server.js --name wa-service

# Artisan commands
php artisan inventory:reconcile           # report global vs pivot stock mismatch
php artisan inventory:reconcile --fix     # align global stock to pivot sum
php artisan reorder:generate              # generate draft PO from low-stock products (daily 02:00)
php artisan crm:sync-segments             # refresh auto segment memberships (daily 01:00)
php artisan crm:generate-reminders       # queue campaign reminder messages (daily 01:15)
php artisan scramble:cache               # warm Scramble OpenAPI cache
php artisan scramble:clear               # invalidate Scramble OpenAPI cache
php artisan seed:demo                    # regenerate full demo dataset (truncates demo tables; --force skips confirm)
php artisan db:seed --class=DemoSeeder --force # explicit full demo dataset (never use on production)

# Formatting
vendor/bin/pint

# Production build
PUPPETEER_SKIP_DOWNLOAD=true npm run build   # CI/deploy skip Puppeteer's Chromium download
```

Production must trigger `php artisan schedule:run` every minute for the scheduled CRM and reorder commands.

## Architecture

- **Controllers**: `app/Http/Controllers/Apps/` — per-module web controllers (~35)
- **API Controllers**: `app/Http/Controllers/Api/` — REST API (Sanctum token auth)
- **Services**: `app/Services/` — ~22 services: AuditLog, BatchService, CashierShiftService, DineOrderService, GoodsReceivingService, LoyaltyService, PaymentGatewayManager, PricingService, PriceListService, PurchaseOrderService, ReorderService, StockMutationService, StockTransferService, UnitConversionService, WhatsAppService, etc.
- **Layouts**: `POSLayout.jsx` (POS), `DashboardLayout.jsx` (admin), `AuthenticatedLayout.jsx` (profile), `GuestLayout.jsx` (auth), `PublicLayout.jsx` (public dine-in)
- **Routes**: `routes/web.php` (~50+ dashboard routes), `routes/api.php` (webhooks + REST API), `routes/auth.php` (Breeze)
- **Inertia shared props**: `HandleInertiaRequests.php` — auth, permissions, notifications (low stock, receivables, payables aging), active shift, store profile, appVersion

## Middleware

| Alias | Class | Applied to |
|-------|-------|------------|
| `permission` | Spatie PermissionMiddleware | Every dashboard route |
| `step_up` | EnsureRecentPasswordConfirmation | Sensitive create/update/delete: roles, users, payment settings, bank accounts, payment confirm |
| `active_shift` | EnsureActiveCashierShift | All POS transaction actions (cart CRUD, hold/resume, checkout) |
| `bot.guard` | EnsureBotGuard | Login/register/forgot-password (honeypot + timer) |
| `registration.enabled` | EnsurePublicRegistrationEnabled | Register route (default: off) |
| `abilities` | CheckAbilities (Sanctum) | API master-data resources only; `/pos/*` and `/auth/*` are auth-only |

## Seeder Chain & First-Install Setup

`DatabaseSeeder` runs only system-essential seeders with permission cache reset before & after:

```
PermissionSeeder → RoleSeeder → PaymentSettingSeeder → DineInSettingsSeeder
```

After seeding, a default `PUSAT` warehouse is created and existing product stock is migrated to the `product_warehouse` pivot.

**No default users.** Admin account, store profile, business type, categories, and main warehouse are created via the first-install setup wizard at `/setup`. Open the root URL after migration; it automatically redirects to `/setup` while `Setting::app_setup_completed` is false. The `setup.notinstalled` middleware redirects to login once setup is done.

**Demo data is opt-in, not part of `DatabaseSeeder`:** run `php artisan db:seed --class=DemoSeeder --force` (or `php artisan seed:demo --force`) for the complete demo dataset. It creates demo outlets `MAL`, `TKB`, and `PUT`; `PUSAT` remains a central non-sales warehouse. Never run the demo seeder on production.

## Inventory Model

`product_warehouse.stock` is the operational source of truth. `products.stock` is maintained as a global aggregate — both must be updated in the same DB transaction for every mutation. Always prefer locking `product_warehouse` rows with `lockForUpdate()` before decrementing. Use `inventory:reconcile --fix` to align global stock after data repairs.

## Critical Gotchas

1. **Permission cache stale after seed** — logout + login again. Seeder resets cache but session still holds old permissions.
2. **Webhooks need public APP_URL** — Midtrans/Xendit won't work with localhost.
3. **Product images need storage:link** — `php artisan storage:link` or images won't render.
4. **Missing migrations cause 500 on new modules** — run `php artisan migrate` for newer modules (purchase orders, goods receiving, supplier returns, stock opname, dine-in, etc.).
5. **Tests force SQLite in-memory** — `phpunit.xml` sets `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`. Don't assume MySQL features. **Set `tax_rate=0` on test Product::create** to avoid PPN changing grand_total.
6. **Dev servers unified** — `composer run dev` starts server + queue + pail + vite together (`php artisan dev`). WA service stays separate.
7. **WhatsApp service separate** — `whatsapp-service/` needs `npm start` in another terminal + `WA_SERVICE_URL` in .env.
8. **CRM campaign auto-send** — requires `wa_enabled=true` + connected device in Settings > WhatsApp.
9. **Version bump on release** — update `APP_VERSION` in `.env` + `.env.example` when tagging.
10. **Concurrency patterns** — all stock mutations (checkout, transfer, receiving, payment) are wrapped in `DB::transaction` with `lockForUpdate()` on affected rows. Never skip the transaction or lock.
11. **Dine-in online payment is disabled** — `payment_option=pay_online` returns 422. Only `pay_at_counter` is accepted.

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
- **Offline mode**: `resources/js/Utils/offlineDb.js` (IndexedDB via `idb`) queues transactions when offline, flushes on reconnect; idempotent via `client_uuid` — server price wins
- **ESC/POS printing**: `resources/js/Utils/escpos.js` (WebUSB, Chromium-only; fallback `window.print()`)
- **Tailwind tokens**: `primary` (indigo), `accent` (cyan), `success` (emerald), `warning` (amber), `danger` (rose)
- **i18n**: Indonesian (`id.json`) and English (`en.json`) in `resources/js/i18n/locales`

## Docs

- Modules: `docs/features/`
- Architecture: `docs/architecture-overview.md`
- Config: `docs/configuration.md`
- Feature index: `docs/feature-index.md`
- Manual QA checklist (QRIS, ESC/POS, offline sync): `docs/testing-manual.md`

## Test Conventions

- Use `RefreshDatabase` trait on every test class
- Seed: `PermissionSeeder → RoleSeeder → UserSeeder` before every test
- Admin: `arya@gmail.com` (super-admin, all permissions); cashier: `cashier@gmail.com`
- **Always call `markEmailAsVerified()`** before `actingAs()` for HTTP controller tests
- `PUSAT` warehouse: `type='main'`, `is_active=true`, `sort_order=0`
- Product needs: `image`, `barcode`, `sku`, `title`, `description`, `category_id`, `buy_price`, `sell_price`, `stock`, `tax_rate=0`
- Attach warehouse stock: `$warehouse->products()->attach($product->id, ['stock' => N])` or `$product->warehouses()->attach($warehouse->id, ['stock' => N])`
- Open shift: `app(CashierShiftService::class)->openShift($cashier, $cashier, $openingCash, null, $warehouse->id)`
- **PHPUnit 12: no `$faker` property** — use `static int $seq = 0` counters or `uniqid()` for unique values
- For API tests: `Sanctum::actingAs($user, ['*'])` — explicit abilities required; TransientToken does not bypass `abilities` middleware

## API Ability System

Master-data API routes (`/api/v1/products`, `/customers`, `/categories`, `/warehouses`, `/suppliers`) enforce Sanctum abilities matching Spatie permission names:

| Route | Ability |
|-------|---------|
| index, show | `{module}-access` |
| store | `{module}-create` |
| update | `{module}-edit` (products/customers/categories) or `{module}-update` (warehouses) |
| destroy | `{module}-delete` |
| suppliers (all verbs) | `suppliers-access` |

`/api/v1/auth/*` and `/api/v1/pos/*` are auth-only (no abilities). Token abilities are stamped at login from Spatie permissions + `'user:read'`. Public registration creates a token with only `'user:read'`.

API tests must use `Sanctum::actingAs($user, ['*'])` or real tokens via `$user->createToken('test', $abilities)->plainTextToken`.

## Route Naming Gotchas

- Price list sidebar link: `price-lists.index` (NOT `settings.price-lists.index`)
- Profile URL: `/dashboard/profile` (NOT `/apps/profile`)
- Public invoice: `/share/transactions/{invoice}?token={access_token}`
