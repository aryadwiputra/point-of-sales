# Multi-Outlet

The POS supports multiple branches (outlets) from a single shared database. Each outlet owns one or more warehouses; the cashier shift picks the warehouse the POS will sell from. This page documents the model and how to use it day-to-day. For staging/production rollout of an existing single-outlet install see the **Rollout** section below.

## Model

- `outlets` is the business boundary above `warehouses`.
- `warehouses.outlet_id` ties every warehouse to its outlet.
- `PUSAT` is the central warehouse/outlet for stock distribution and is **not sales-enabled**.
- Sales outlets (branches) each have a `branch`-type warehouse and can open cashier shifts.
- Cashier assignments are stored in `user_outlets` (one default per user).
- Active outlet context is session-based; the active shift warehouse/outlet is the source of truth and locks outlet switching while a shift is open.
- Global fallback remains for legacy single-outlet installations.

## Scope Policy

- Customers, loyalty, suppliers, receivables, and payables remain global.
- Transactions, stock, shifts, purchasing, returns, reports, settings, pricing, and dine-in data use outlet context.
- `PUSAT` is the central warehouse and is not sales-enabled.
- A cashier uses one active shift and one outlet warehouse at a time.
- Global configuration remains a fallback for legacy single-outlet installations.

## Day-to-Day Operations

After the first install the active outlet is set in the navbar selector (`OutletSwitcher`). While a shift is open the selector is locked to the shift's warehouse/outlet.

- **Opening a shift** — pick the branch warehouse from the cashiers' assigned active warehouses only.
- **Switching outlets** — close all open shifts first; the selector allows switching between assigned active outlets.
- **Stock transfers** — `PUSAT → branch` is the typical replenishment path; cross-outlet transfers require both endpoints to be assigned to the operator.
- **Per-outlet settings** — store profile, printer, payment settings, bank accounts, pricing, vouchers, dine-in, WhatsApp, and sales target each have an outlet override and a global fallback.
- **Reports** — sales, profit, and dashboard reflect the operator's accessible outlets. Use the warehouse filter for finer selection.
- **Receivables and payables** — remain in a global ledger; visibility and payment authorization derive from the source transaction/purchase-order outlet.

## Rollout (existing single-outlet installs)

> Read-only until the business approves every data mapping. Do not run `--fix` or `--strict` until staging has been exercised end-to-end.

### Preflight

1. Back up the database and restore that backup into staging.
2. Run `php artisan migrate --pretend`, then `php artisan migrate` on staging.
3. Run `php artisan outlet:audit` and `php artisan outlet:legacy-audit`.
4. Run `php artisan inventory:reconcile` and save the output.
5. Do not run `inventory:reconcile --fix` until the team confirms that warehouse pivot stock is authoritative.
6. Classify every warehouse-less transaction, shift, stock mutation, purchase, receiving, return, and opname.
7. Assign every active user to the correct outlet and verify one default outlet.

## Outlet Setup

Create or verify:

| Code | Role | Sales |
| --- | --- | --- |
| `PUSAT` | Central warehouse | No |
| `MAL` | Malabar outlet | Yes |
| `TKB` | Taman Kencana outlet | Yes |
| `PUT` | Puter outlet | Yes |

For every sales outlet:

- Link one active branch warehouse.
- Attach product stock rows and load opening stock through approved transfers or opname.
- Assign cashiers and managers through user outlet assignments.
- Configure store profile, printer, payment settings, bank accounts, pricing, vouchers, dine-in tables, WhatsApp, and sales target as required.

## Pilot Checklist

Run the following with a test user assigned to one outlet:

- Switch only between assigned active outlets.
- Confirm outlet switching is blocked while a shift is open.
- Confirm PUSAT cannot open a sales shift.
- Open and close a shift with cash movement.
- Complete cash, bank transfer, QRIS, and split-payment sales.
- Confirm transaction, stock, receipt, and payment webhook outlet context.
- Test offline sync and confirm it cannot post without the correct active shift.
- Test return, stock opname, transfer, purchase order, goods receiving, and supplier return.
- Test dine-in QR ordering at the correct outlet table.
- Verify sales, profit, insights, receivable, payable, and dashboard totals for the active outlet.
- Verify an Outlet A user cannot read or mutate Outlet B records.
- Run `php artisan outlet:audit --strict` and require success.

## Production Gate

Do not enable the second sales outlet until all of the following are true:

- `php artisan outlet:audit --strict` exits successfully.
- `php artisan outlet:legacy-audit` output has been reviewed and mapped where appropriate.
- Stock reconciliation output is clean or formally accepted.
- Staging pilot passes and the rollback plan is documented.
- Backups and queue/scheduler/WhatsApp monitoring are confirmed.

The final major release should be `v3.0.0` only after this gate and the first production pilot are accepted.
