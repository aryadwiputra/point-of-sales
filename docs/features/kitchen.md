# Kitchen Operations (KDS)

Kitchen operations route sold items to kitchen stations, create one ticket per source round per station, and let kitchen staff move items through `queued → cooking → ready` on a polling display. The KDS never shows prices, costs, customer identity, or payment state.

## Triggers

Tickets are created inside the same database transaction as the sale:

- Dine-in QR order accepted from **Pesanan Dine-In**.
- Staff round submitted from POS to a table bill.
- POS checkout with `order_type = takeaway` or `delivery`.

Plain `in_store` checkout does not create a ticket because it belongs to the dine-in bill flow. Offline POS sales create tickets after a successful sync because the sync path calls the same checkout service.

## Routing

`KitchenTicketService::routeFor()` resolves in this order:

1. `product_kitchen_station_overrides` for the product and outlet.
2. `kitchen_station_categories` for the product category and outlet.
3. No match: the item is skipped and an audit entry `kitchen.ticket_skipped` is written.

A station must be active. Composite products route by the composite product, not by their components. There is no default station fallback.

The station admin page warns about active categories whose active products have neither a mapping nor an override in the selected outlet.

## Ticket and item status

Item statuses:

- `queued`
- `cooking`
- `ready`
- `cancelled`

Allowed transitions are `queued → cooking`, `cooking → ready`, `cooking → queued`, and `ready → cooking`. Cancellation is final.

Ticket status is derived from its items:

- Any item still `queued` or `cooking` → `open`.
- All non-cancelled items `ready` → `ready`.
- All items `cancelled` → `cancelled`.
- The kitchen clears a `ready` ticket → `closed`.

Only the kitchen closes a ticket. Payment and settlement never change a ticket.

Voiding a dine-in bill item cancels the matching ticket item. Splitting a bill does not move ticket items. Moving or merging tables does not touch tickets because the KDS reads the live table name from `order.session.table`.

## Permissions and roles

| Permission | Use |
|---|---|
| `kitchen-access` | Open the KDS and read the board. |
| `kitchen-process` | Change item status, run bulk cooking/ready, and clear a ready ticket. |
| `kitchen-manage` | Manage stations, mappings, user assignment, and the `kitchen_enabled` setting. |

The `kitchen` role has only `kitchen-access` and `kitchen-process`. It does not have `dashboard-access`, so it cannot open financial or store settings. Kitchen-only users are redirected to the KDS after login.

Manager and super-admin receive all three permissions. Cashier receives none.

## Admin

Open **Dapur → Station Dapur** (`kitchen.stations.index`) to:

- Create, edit, deactivate, and delete stations.
- Map categories to one station per outlet.
- Assign kitchen users to many stations.
- Override individual products to a station.
- Disable or enable the kitchen display.

A station with any ticket cannot be deleted; deactivate it instead. Existing tickets on an inactive station remain visible until cleared.

## Settings

`kitchen_enabled` is a global boolean with fallback `true`. When disabled, no new tickets are created and the KDS shows a disabled notice; existing tickets remain readable.

## Routes

| Method | URI | Name | Permission |
|---|---|---|---|
| GET | `/dashboard/kitchen` | `kitchen.index` | `kitchen-access` |
| GET | `/dashboard/kitchen/board` | `kitchen.board` | `kitchen-access` |
| PATCH | `/dashboard/kitchen/items/{item}/status` | `kitchen.items.status` | `kitchen-process` |
| POST | `/dashboard/kitchen/tickets/{ticket}/cooking` | `kitchen.tickets.cooking` | `kitchen-process` |
| POST | `/dashboard/kitchen/tickets/{ticket}/ready` | `kitchen.tickets.ready` | `kitchen-process` |
| POST | `/dashboard/kitchen/tickets/{ticket}/clear` | `kitchen.tickets.clear` | `kitchen-process` |
| GET | `/dashboard/kitchen/stations` | `kitchen.stations.index` | `kitchen-manage` |
| POST | `/dashboard/kitchen/stations` | `kitchen.stations.store` | `kitchen-manage` |
| PATCH | `/dashboard/kitchen/stations/{station}` | `kitchen.stations.update` | `kitchen-manage` |
| DELETE | `/dashboard/kitchen/stations/{station}` | `kitchen.stations.destroy` | `kitchen-manage` |
| POST | `/dashboard/kitchen/settings` | `kitchen.settings` | `kitchen-manage` |

## Data model

- `kitchen_stations` — outlet, name, sort order, active flag.
- `kitchen_station_categories` — one category mapping per outlet.
- `product_kitchen_station_overrides` — one product override per outlet.
- `kitchen_station_user` — kitchen user assignment.
- `kitchen_tickets` — source round, station, outlet, status, order type, timestamps.
- `kitchen_ticket_items` — product name, quantity, modifier text, note, status, cancellation reason, timestamps.

Tickets snapshot display data only. They store no money fields.

## Testing

```bash
rtk php artisan test --filter=Kitchen
rtk php artisan test
PUPPETEER_SKIP_DOWNLOAD=true npm run build
```

Manual checks:

- Map two stations and verify one ticket per station after accepting a QR order.
- Verify a POS takeaway checkout creates a ticket and an in-store checkout does not.
- Verify the KDS shows the new table name after moving a table.
- Verify voiding a bill item cancels the ticket item.
- Verify a kitchen user only sees assigned stations and cannot open `/dashboard`.