# Menu Modifiers

Modifier groups let a single product carry F&B choices such as size, sugar level, ice level, or toppings without creating a separate SKU per combination.

## Data model

- `modifier_groups` — name, `selection_type` (`single` or `multiple`), `min_select`, `max_select`, `is_active`, `sort_order`.
- `modifiers` — options inside a group: `name`, `price_delta` (integer rupiah, may be negative), `is_default`, `is_active`, `sort_order`.
- `product_modifier_groups` — attaches a group to a product and controls the per-product order.
- `product_modifier_overrides` — optional per-product price for one option. The effective delta is the override when present, otherwise the option's global `price_delta`.

## Snapshot tables

Cart lines, transaction details, and dine-in order items each store their own copy of the chosen options:

- `cart_modifiers`
- `transaction_detail_modifiers`
- `dine_order_item_modifiers`

Each row keeps `modifier_id` and `modifier_group_id` (both nullable on delete), plus `group_name`, `name`, and `price_delta`. Historical receipts therefore remain correct even after the library changes.

## Pricing

`PricingService` adds the sum of the line's modifier deltas to the item's base unit price before discounts. Percentage and quantity-break discounts apply to the product price only; bundle and buy-get rules use the modified unit price. The server always recomputes prices from option ids — clients never send prices.

## Cart identity

A cart line is identified by product, unit, note, and the set of chosen option ids. `line_signature` (sha1) makes two lines with different options or notes stay separate. The web and API cart endpoints merge only when the signature matches.

## Stock behavior

Products have a `track_stock` flag (default `true`). When it is `false`:

- the product stays visible in POS and the self-order menu even at zero stock;
- checkout skips the stock gate, stock decrement, mutation log, and batch allocation;
- it is excluded from low-stock notifications, reorder suggestions, and `isLowStock()`.

Composite products always consume their component stock, regardless of the flag. Cost of goods is still calculated for non-stocked items.

## Permissions

`modifiers-access`, `modifiers-create`, `modifiers-edit`, `modifiers-delete`. Super-admin and manager receive them; cashier does not. The library lives at **Dashboard → Modifier Menu** (`/dashboard/modifiers`).

## Known limits (Phase A)

- No product variants: every difference is a modifier.
- No combo or bundle product UI; the existing composite product feature is unchanged.
- Editing a cart line's modifiers is done by removing the line and adding it again.
- Quantity-break pricing is evaluated per line, not per combined product quantity.
