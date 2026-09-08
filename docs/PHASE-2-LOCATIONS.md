# Phase 2 — Inventory Locations and Stock Ledger

Implemented on `phase-2-location-ledger`.

## Data model

Schema version 3 adds:

- `ssw_locations`
- `ssw_location_stock`
- `ssw_stock_movements`

`ssw_stock_movements` is an append-only operational audit ledger. Every recorded mutation stores product/variation, location, before, delta, after, movement type, source, source reference, user, note and timestamp.

## Stock rules

- A `Main warehouse` location is created automatically.
- Existing WooCommerce stock can be seeded into Main when a product is first opened in the location workflow.
- WooCommerce stock is the sum of balances at active sellable locations.
- Non-sellable locations do not contribute to storefront availability.
- Manual adjustments are recorded as movements before Woo aggregate stock is synchronized.
- Transfers create paired `transfer_out` and `transfer_in` movements and therefore do not intentionally change aggregate stock.
- Negative location stock is rejected.

## Admin

The **Locations** submenu allows WooCommerce managers to:

- create and edit locations;
- mark locations active/inactive and sellable/non-sellable;
- load a simple product or variation by WooCommerce ID;
- inspect stock by location;
- apply signed manual adjustments with a reason/note;
- transfer stock between active locations.

Writes are guarded by `manage_woocommerce` and WordPress nonces.

## Current limits

- Location seeding is lazy per product/variation, rather than a full background backfill.
- Browser-level WordPress/WooCommerce smoke tests are not yet automated.
- Transfer failure handling uses compensating audit movement if the second leg fails. A later hardening pass may move both transfer legs into one transaction boundary.
