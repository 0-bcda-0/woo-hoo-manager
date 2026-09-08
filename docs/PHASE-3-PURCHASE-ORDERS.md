# Phase 3 — Purchase Orders and Receiving

Implemented on `phase-3-purchase-orders`.

## Workflow

Supported statuses:

`Draft → Approved → Ordered → Shipped → Partially received → Received`

`Cancelled` is a terminal path from open pre-receipt states. Invalid backwards transitions are rejected.

## Data model

Schema version 4 adds:

- `ssw_purchase_orders`
- `ssw_purchase_order_items`
- `ssw_po_receipts`
- `ssw_po_receipt_items`

Purchase orders store supplier, destination location, currency, expected date, totals and status. Items store WooCommerce product/variation ID, supplier SKU, ordered/received quantity and unit cost.

## Receiving safety

- A receipt uses a deterministic SHA-256 idempotency key derived from PO ID and a client request token.
- The receipt table has a unique index on that key.
- Retrying the same receipt request returns the already-created receipt instead of applying stock again.
- Receipt, receipt items, location balance changes, movement ledger writes and PO received quantities execute inside one database transaction.
- Stock movements can participate in the caller-owned transaction.
- WooCommerce aggregate stock is synchronized once per affected product after the receipt transaction commits.
- Over-receiving an item is rejected.
- Partial receipts update PO status to `partially_received`; all quantities received updates it to `received`.

## Admin

The **Purchase Orders** submenu allows WooCommerce managers to:

- create draft POs for an active supplier and destination location;
- add simple products or variations by WooCommerce ID;
- store supplier SKU, quantity and unit cost;
- approve, mark ordered, mark shipped or cancel according to valid transitions;
- receive partial or full quantities into a selected active location;
- see ordered, received and remaining quantities.

All writes use `manage_woocommerce` capability checks and WordPress nonces.

## Current limits

- The first UI uses fixed item-entry rows rather than a dynamic product search/repeater.
- PDF/email PO documents are not implemented yet.
- Existing low-stock CSV purchase-order export remains separate; consolidation into the real PO workflow is a later UX task.
- Full browser-level WordPress/WooCommerce integration tests are still recommended before production use.
