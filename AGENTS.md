# Point of Sales — Agent Guide

Laravel POS with an Inertia/React frontend, multi-warehouse inventory, and a separate optional WhatsApp service.

## Hard constraints
- Treat `product_warehouse.stock` as operational stock; keep `products.stock` aggregate in sync in the same transaction. Lock affected inventory rows before mutation.
- Never run the demo seeder against production; it truncates/rebuilds demo data.
- Use npm and the committed `package-lock.json`; do not add alternative lockfiles.
- Do not assume MySQL behavior in tests: PHPUnit uses in-memory SQLite.
- Preserve Sanctum abilities and Spatie permissions when changing API authorization; API tests need explicit token abilities.

## Quick start

```bash
composer install
PUPPETEER_SKIP_DOWNLOAD=true npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
composer run dev
```

First run redirects to `/setup` to create the admin and store profile; there are no default accounts. `composer run dev` starts the Laravel dev stack (server, queue, logs, and Vite). The optional WhatsApp gateway runs separately: `cd whatsapp-service && npm install && npm start`.

## Verify changes

```bash
php artisan test
php artisan test --filter=TestName
vendor/bin/pint
PUPPETEER_SKIP_DOWNLOAD=true npm run build
```

There is no frontend lint or typecheck script in the root `package.json`. Run focused tests with PHPUnit's `--filter`; tests use SQLite in-memory and array/sync drivers. Set `tax_rate=0` on test products when tax is not under test.

## Structure and references

- `app/Http/Controllers/Apps/`: dashboard modules; `Api/` and `Reports/` contain API and report controllers.
- `app/Services/`: domain operations, including checkout, stock, purchasing, and payment workflows.
- `resources/js/`: Inertia React app; Vite entry is `resources/js/app.jsx`.
- `routes/web.php`, `routes/api.php`, `routes/console.php`: web, REST, and scheduled command routes.
- `whatsapp-service/`: separate Node.js WhatsApp gateway.
- Module and operational detail: `docs/architecture-overview.md`, `docs/feature-index.md`, `docs/features/`, `docs/configuration.md`, `docs/testing-manual.md`.

## Non-obvious behavior

- Root `DatabaseSeeder` seeds essential system configuration, not users or the full demo dataset. First-install setup creates the initial admin; demo data is opt-in via `php artisan seed:demo --force`.
- `php artisan test` uses SQLite `:memory:`; avoid database-specific assumptions. For API tests use `Sanctum::actingAs($user, ['*'])` or a real token with the needed abilities.
- `composer run dev` is the root development command. The WhatsApp service is not included and requires its own `npm start` process when used.
- Laravel's scheduler is defined in `routes/console.php`: CRM segment sync (01:00), CRM reminders (01:15), reorder generation (02:00), and pending-transaction expiry (hourly). Production must invoke `php artisan schedule:run` every minute.
- Midtrans/Xendit webhooks require a publicly reachable `APP_URL`; product images require `php artisan storage:link`.
- Dine-in online payment is currently rejected; only `pay_at_counter` is supported.
- After permission seeding, an existing session may retain stale permissions; log out and back in.

## Workflow

`DatabaseSeeder` runs only system-essential seeders with permission cache reset before & after:

```
PermissionSeeder → RoleSeeder → PaymentSettingSeeder → DineInSettingsSeeder
```

After seeding, a default `PUSAT` warehouse is created and existing product stock is migrated to the `product_warehouse` pivot.

**No default users.** Admin account, store profile, business type, categories, and main warehouse are created via the first-install setup wizard at `/setup`. Open the root URL after migration; it automatically redirects to `/setup` while `Setting::app_setup_completed` is false. The `setup.notinstalled` middleware redirects to login once setup is done.

**Demo data is opt-in, not part of `DatabaseSeeder`:** run `php artisan db:seed --class=DemoSeeder --force` (or `php artisan seed:demo --force`) for the complete demo dataset. It creates demo outlets `MAL`, `TKB`, and `PUT`; `PUSAT` remains a central non-sales warehouse. Demo accounts (password `password`): `arya@gmail.com` (super-admin, all outlets), `manager@gmail.com` (manager role, MAL+TKB), `cashier@gmail.com` (cashier, MAL). Never run the demo seeder on production. Full dataset details: `docs/demo-data.md`.

**Email verification is disabled** — `User` no longer implements `MustVerifyEmail`, dashboard routes carry no `verified` middleware, and the verification routes/controllers/pages are removed. `markEmailAsVerified()` is still available via the retained trait (used by seeders and tests).

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
- Admin: `arya@gmail.com` (super-admin, all permissions); manager: `manager@gmail.com`; cashier: `cashier@gmail.com`
- **Always call `markEmailAsVerified()`** before `actingAs()` for HTTP controller tests (email verification is disabled, but the trait method remains available)
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
| `/api/v1/pos/*` (all verbs) | `pos-access` |

`/api/v1/auth/*` is auth-only (no abilities). Token abilities are stamped at login from Spatie permissions + `'user:read'`. Public registration creates a token with only `'user:read'` (so it cannot reach `/pos/*`). The `pos-access` permission is granted to `cashier`, `manager`, and `super-admin` roles by the seeders. Existing mobile tokens must re-login after this change to receive `pos-access`.

API tests must use `Sanctum::actingAs($user, ['*'])` or real tokens via `$user->createToken('test', $abilities)->plainTextToken`.

## Skills Integration

**LOAD SKILL BEFORE EXECUTING** — do not skip skill loading:

| Trigger | Skill | Action |
|---------|-------|--------|
| Before creating features, components, adding functionality | `skill:brainstorming` | Classify request → present design → get approval |
| Multi-step task after approved design | `skill:writing-plans` | Create implementation plan → execute via `skill:executing-plans` |
| Code review for over-engineering | `skill:ponytail-review` | Check diff for unnecessary complexity, unused abstractions |
| E2E browser testing | `skill:webapp-testing` | Use Playwright with `scripts/with_server.py` |

**Default Mode: Ponytail** (`skill:ponytail` active) — stdlib first, YAGNI ruthless, native features before libraries.

**Session Tracking:** After session ends, invoke `skill:obsidian-cli` to export session note to Obsidian vault.

## Route Naming Gotchas

- Price list sidebar link: `price-lists.index` (NOT `settings.price-lists.index`)
- Profile URL: `/dashboard/profile` (NOT `/apps/profile`)
- Public invoice: `/share/transactions/{invoice}?token={access_token}`
- README contribution flow: branch features from `development` and target PRs to `development`; releases go to `main` through a release branch. Run `php artisan test` before submitting. Do not infer CI/deploy behavior from this checkout; no workflow files are present here.
