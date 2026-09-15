# Multi-Outlet Rollout Runbook

This runbook is for staging and production rollout of the shared-database outlet model.
It is intentionally read-only until the business approves each data mapping.

## Scope Policy

- Customers, loyalty, suppliers, receivables, and payables remain global.
- Transactions, stock, shifts, purchasing, returns, reports, settings, pricing, and dine-in data use outlet context.
- `PUSAT` is the central warehouse and is not sales-enabled.
- A cashier uses one active shift and one outlet warehouse at a time.
- Global configuration remains a fallback for legacy single-outlet installations.

## Preflight

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
