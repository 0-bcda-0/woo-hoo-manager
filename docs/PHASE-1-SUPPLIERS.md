# Phase 1 — Supplier Management

Implemented on `phase-1-suppliers`.

## Data

Schema version 2 adds:

- `ssw_suppliers`
- `ssw_supplier_products`

A WooCommerce product or variation can have multiple supplier relationships. Exactly one can be marked preferred. Purchasing metadata includes supplier SKU, cost, currency, MOQ, units per box and lead time.

## Admin

A new **Suppliers** submenu under the existing Stock Manager menu allows WooCommerce managers to:

- create, edit and delete suppliers;
- store contact/default purchasing details;
- load a product or variation by WooCommerce ID;
- assign multiple suppliers to that product/variation;
- set cost, currency, MOQ, units per box and lead time;
- choose one preferred supplier;
- remove relationships.

All writes use `manage_woocommerce` capability checks and WordPress nonces. Inputs are sanitized before persistence.

## Verification

Lightweight deterministic tests cover schema migration/idempotency, relation normalization and single-preferred-supplier behavior on PHP 7.4, 8.1 and 8.3. Full WordPress/WooCommerce browser smoke testing remains required before treating the feature as production-ready.
