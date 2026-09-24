# Changelog

All notable application releases are listed here. Git tags using the same
`vMAJOR.MINOR.PATCH` version are the authoritative release identifiers.

## [Unreleased]

### Added

- `manager` role and `manager@gmail.com` demo account (scoped to outlets MAL + TKB) to demonstrate multi-outlet RBAC without super-admin privileges.
- `docs/demo-data.md` — single reference for the demo dataset, credentials, reset steps, and production-safety guidance. Linked from `README.md`, `docs/README.md`, and `docs/getting-started.md`.
- Demo seeder now seeds the full settings surface: store NPWP/NIB, default tax rate, printer, WhatsApp, discount-approval thresholds, and loyalty earn/redeem + tier thresholds.

### Changed

- **Email verification disabled**: `User` no longer implements `MustVerifyEmail`; the `verified` middleware, verification routes/controllers, and `Auth/VerifyEmail` page are removed. The setup wizard and public registration now land users directly on the dashboard. `markEmailAsVerified()` remains available via the retained trait for seeders/tests.
- `DineInSettingsSeeder` now sets `dine_in_pay_online_enabled = 0` to match the enforced behavior (only `pay_at_counter` is accepted).
- `seed:demo` and `DemoSeeder` output now lists all three demo accounts with role and outlet scope.

## [v3.0.2] - 2026-09-18

### Changed

- Refactor: `PosApiController::checkout()` now reuses `CheckoutService` instead of duplicating ~250 LOC of transaction creation, pricing preview, stock decrement, FEFO batch consumption, and receivable creation logic. API checkout behavior is unchanged; web and API flows now share the same code path through the `CheckoutContext` DTO.

## [v3.0.1] - 2026-09-18

### Added

- CI workflow now runs `php artisan test --compact` on every push and PR, so broken tests block merges before they reach `main`.
- `sentry/sentry-laravel` SDK installed; environment-ready for Sentry DSN. SDK stays inactive when `SENTRY_LARAVEL_DSN` is empty.
- `tests/Feature/Transactions/CheckoutServiceTest.php` covers cash-below-total rejection, exact-cash success, split-tender creation, and empty-cart 422.

### Changed

- Refactored: extracted the ~250 LOC `DB::transaction` closure from `TransactionController::store()` into a dedicated `App\Services\CheckoutService`. Controller now builds a `CheckoutContext` DTO, calls the service, and handles only post-commit side effects (discount approval, gateway tenders, redirect). Reduces controller complexity and makes the checkout core directly unit-testable.
- `docs/architecture-overview.md` updated with current counts (194 routes, 59 models, 97 migrations, 5 layouts) and middleware table including `abilities`, `setup.notinstalled`, `SetLocale`, `SecureHeaders`, `EnforceAbsoluteSessionLifetime`, and `HandleInertiaRequests`.
- `.env.example` now includes all environment variables actually consumed by `config/*.php`: `MIDTRANS_SERVER_KEY`, `MIDTRANS_IS_PRODUCTION`, `XENDIT_SECRET_KEY`, `XENDIT_IS_PRODUCTION`, full `INERTIA_SSR_*` block, `SECURITY_BOT_GUARD_*` group, `SECURITY_SESSION_ABSOLUTE_LIFETIME_SECONDS`, `AUTH_PASSWORD_TIMEOUT`, optional mail/log keys, and `SENTRY_LARAVEL_DSN`.

### Performance

- Composite index `transactions (warehouse_id, created_at)` added to support dashboard revenue-trend queries at scale.

### Hygiene

- Deleted local `feature/split-payment` branch (already merged into development).
- Confirmed `bun.lock` already excluded from git tracking (per AGENTS.md).

### Notes

- No new migrations required; one added: `2026_09_18_000001_add_warehouse_created_at_index_to_transactions.php`.
- Sentry integration is installed but disabled until a DSN is provided; no production behavior change.

## [v3.0.0] - 2026-09-18

### Breaking

- Outlet context is now a first-class security and operational boundary. Existing installs must run `php artisan outlet:audit --strict` after upgrade; multi-outlet data isolation is enforced and arbitrary warehouse/outlet IDs are rejected where they were previously global.
- `warehouses.outlet_id` is required for new operational records. Records with `outlet_id IS NULL` are treated as legacy and remain readable only while a single active outlet exists.
- The cashier shift now requires a sales-enabled warehouse; `PUSAT` can no longer open a sales shift.

### Added

- Multi-outlet architecture: `outlets`, `user_outlets` assignments, `OutletAccessService` (`canUseWarehouse`, `canSellAtWarehouse`, `salesWarehousesFor`, `warehousesFor`, `defaultOutlet`, `accessibleOutlets`, `activeOutlet`), outlet switcher in the navbar, session-based active outlet with shift locking.
- Per-outlet settings with global fallback: store profile, printer/auto-print, payment settings, bank accounts, WhatsApp, sales target, pricing rules, price lists, dine-in areas/tables, customer vouchers, customer campaigns.
- Operational scoping for transactions, carts, returns, stock mutations, stock opname, stock transfers, purchase orders, goods receiving, supplier returns, API POS endpoints, and DineOrder table-vs-shift outlet validation.
- Reporting and dashboard scoped by accessible warehouses with single-outlet legacy fallback.
- Outlet lifecycle controls: create outlets, edit basic fields, deactivate, delete blocked when operational history exists; setup wizard creates the central `PUSAT` plus user-defined branch outlets.
- `php artisan outlet:audit` (read-only) and `--strict` (release gate) plus `php artisan outlet:legacy-audit` for warehouse-less record classification.
- Setup wizard now asks for branch outlets (code/name/warehouse code/name/optional address/phone) on a dedicated step.
- Demo seeding split: `php artisan migrate --seed` is production-safe and only provisions `PUSAT`; `php artisan db:seed --class=DemoSeeder --force` (or `seed:demo --force`) loads the canonical multi-outlet demo dataset.

### Changed

- `PUSAT` is central-only and not sales-enabled; cashier shifts and POS use sales-enabled branch warehouses.
- Per-receivable/per-payable access is derived from the source transaction/purchase-order warehouse/outlet; the ledger remains global.
- `docs/multi-outlet.md` documents the model and day-to-day operations; the rollout runbook is the second half of the same page.
- Demo data shifted to four outlets (`PUSAT`, `MAL`, `TKB`, `PUT`) with sales shifts/transactions only on branch warehouses.

### Notes

- Production rollout of an existing single-outlet install must follow `docs/multi-outlet.md` (Rollout section) including `php artisan outlet:audit --strict` before activating the second sales outlet.
- No new migrations are required for fresh installs beyond the outlet series shipped over recent releases; existing installs must run `php artisan migrate` before upgrading.

## [v2.11.0] - 2026-09-13

### Added

- Added split payments with up to two tenders per transaction.
- Added tender-level payment tracking, gateway webhooks, receipt output, and shift cash summaries.

### Improved

- Transaction exports now show individual tender methods for split payments.

### Notes

- Split payments are online-only in this release; split refunds remain cash refunds.

## [v2.10.6] - 2026-09-13

### Added

- Added `php artisan seed:demo` command for one-command full demo dataset regeneration (with `--force` to skip confirmation; truncates 19 tables).
- Demo accounts created by `UserSeeder` are now pre-verified, so seeded admin/cashier can log in without email verification.
- Added migration for `cashier_shifts.cash_in_total` / `cash_out_total` columns so fresh installs match the shift summary schema.

## [v2.10.5] - 2026-09-12

### Fixed

- Fixed cashier shift closing to persist only columns present in the shift schema.
- Added a full demo seeder that runs the required seeders in dependency order.
- Ensured demo products are linked to the primary warehouse before stock transfers.

### Improved

- Improved transaction customer selection responsiveness and desktop overflow handling.
- Reorganized order details and cash payment controls for faster checkout.
- Added dynamic quick cash amounts, exact-payment action, and clearer change feedback.

## [v2.10.4] - 2026-09-12

### Fixed

- Reused the seeded primary warehouse during first-install setup instead of attempting to create a duplicate `PUSAT` warehouse.
- Fixed setup wizard submission when transforming form data before posting.
- Added setup warehouse coverage for reuse, rename, duplicate validation, and versioned setup state.

## [v2.10.3] - 2026-09-12

### Fixed

- Stabilized the first-install setup wizard, localization, root redirect, and migration rollback behavior.

## [v2.10.2] - 2026-09-12

### Fixed

- Fixed guided-tour behavior and replay handling.

## [v2.10.1] - 2026-09-12

### Fixed

- Fixed walk-in checkout behavior and stabilized the related documentation.

## [v2.10.0] - 2026-09-12

### Added

- Added automatic receipt printing and ESC/POS WebUSB support.

## [v2.9.0] - 2026-09-12

### Added

- Added cashier shift cash movements, X/Z reports, and order types.

## [v2.8.0] - 2026-09-12

### Added

- Added offline transaction synchronization and dynamic QRIS support.

## [v2.7.0] - 2026-09-12

### Added

- Added tour replay and the setup checklist.

## [v2.6.0] - 2026-09-12

### Added

- Added guided tours for onboarding.

## [v2.5.0] - 2026-09-12

### Added

- Added the first-install setup wizard and unified local development command.

## [v2.4.0] - 2026-09-12

### Added

- Added dine-in QR menu and customer self-order workflow.
