# Woo Hoo Manager — Data Model

**Scope authority:** `docs/SCOPE-LOCK.md`. This model exists only to support approved features and required infrastructure.

## Principles
- WooCommerce remains canonical for product/order identity and aggregate sellable stock.
- Relational entities, ledgers and time-series facts may use custom `$wpdb->prefix` tables.
- Migrations use versioned `dbDelta()` and must be retry-safe.
- Persisted money uses decimal-safe values plus currency context.
- Do not add tables/fields for unapproved product features.

## Product settings/meta
Preserve existing low-stock threshold and units-per-box behavior. Additional settings are allowed only when required by an approved feature, e.g. safety-stock days for the approved 90-day plan/what-if simulator.

## Custom tables

### `ssw_suppliers`
Supplier identity/contact, default currency/lead time, notes, active state, timestamps.

### `ssw_supplier_products`
Supplier-product/variation relationship with supplier SKU, cost, currency, MOQ, units-per-box, lead time and preferred flag.

### `ssw_purchase_orders`
PO header with number, supplier, location, status, currency, dates/ETA, notes and audit timestamps/users.

### `ssw_purchase_order_items`
PO lines with product, supplier-product relation, ordered/received quantity and unit cost. Remaining quantity is derived.

### `ssw_po_receipts` / `ssw_po_receipt_items`
Idempotent receiving events and lines linked to PO items and stock movements.

### `ssw_locations`
Inventory locations including Main warehouse, code, active/sellable state and metadata.

### `ssw_location_stock`
Current product/location balance for fast reads.

### `ssw_stock_movements`
Immutable audit ledger: product, location, before/delta/after, movement type, source, user, note, timestamp.

### `ssw_barcodes`
Product/variation barcode mappings (GTIN/EAN/UPC etc.), uniquely indexed as appropriate.

### `ssw_sales_daily`
Retry-safe daily product/variation sales facts used by approved risk, health, dead-stock, planning and reporting features.

### `ssw_inventory_snapshots_daily`
Daily product/location quantity plus cost/retail context used by approved dead-stock, GMROI, health and reporting features.

### `ssw_product_metrics_daily`
Precomputed deterministic facts used by approved features. Appropriate fields include:
- units/velocity windows;
- weighted velocity;
- available quantity / stock cover;
- projected stockout date;
- average realized price;
- demand variability only where needed internally by approved health/alerts;
- health score/component facts;
- last sale / days-since-sale / OOS duration facts;
- contribution/cost facts needed by approved Pareto/GMROI;
- confidence/data-quality state.

**Do not store or calculate ABC/XYZ classes.**

### `ssw_forecasts`
Deterministic forecast snapshots required by approved Revenue at Risk, 90-day purchasing/cash planning, What-if and reporting. Forecasting remains transparent infrastructure, not an extra product module.

### `ssw_alerts`
Persisted/deduplicated Smart Alerts: type, severity, relevant entity IDs, dedupe key, state/timestamps, context payload.

### `ssw_reports`
Weekly Executive Report deterministic snapshot/metadata. Optional AI narrative is supplementary.

## Aggregate stock synchronization
For managed products:

`WooCommerce sellable stock = sum(active sellable location balances)`

Transfers between sellable locations use paired movements and do not change aggregate stock. PO receipts/adjustments update a location then synchronize aggregate through WooCommerce stock APIs with recursion guards.

## Reserved / available stock
Reserved stock is derived from eligible WooCommerce order behavior where reliable, not an independently editable balance. UI must disclose when it cannot be reliably derived.

## Background jobs
Allowed jobs exist only to support approved capabilities:
1. historical order aggregation;
2. daily inventory snapshot;
3. deterministic product metric/forecast calculation;
4. bundle co-purchase aggregation;
5. Smart Alert evaluation;
6. weekly report generation.

Jobs must be bounded and retry-safe and must not run expensive work on normal storefront requests.

## Data-quality states
Use `insufficient_data`, `low_confidence`, `normal`, `high_confidence` (or compatible states) where approved forecast-dependent outputs need them. Prefer missing/insufficient-data messaging over false precision.

## Retention
Do not automatically purge audit stock movements or PO receipts. Retain enough deterministic sales/inventory history to support the approved GMROI, dead/slow-stock, risk, planning and report windows. No retention requirement may be justified by an unapproved feature.