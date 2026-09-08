# Woo Hoo Manager — Data Model

This document defines the intended persistence model. Exact schema details may be refined during implementation, but semantic meanings must remain stable.

## Principles

- WooCommerce remains canonical for product/order identity and aggregate sellable stock.
- Product meta is acceptable for a small number of product-level settings.
- Relational entities, ledgers and time series belong in custom tables.
- Use `$wpdb->prefix` and `dbDelta()`/versioned migrations; never hard-code `wp_`.
- All tables need appropriate indexes for product, date, status and foreign-key-like IDs even though WordPress does not require SQL foreign keys.
- Monetary values store decimal values plus currency; do not use floating-point math for persisted money.

## Product meta / settings

Existing metadata such as low-stock threshold and units-per-box should be preserved/migrated, not duplicated unnecessarily.

Potential additional product-level settings:

- barcode/GTIN only if a dedicated barcode mapping table is not required for multiple codes;
- safety stock days override;
- preferred supplier relationship ID;
- forecast/reorder exclusions;
- product lifecycle state.

## Proposed custom tables

Names below omit the runtime WordPress prefix.

### `ssw_suppliers`

Supplier master data: `id`, `name`, contact fields, address/notes, `currency`, `lead_time_days`, `minimum_order_value`, `active`, timestamps.

### `ssw_supplier_products`

Many-to-many supplier-product relation: `id`, `supplier_id`, `product_id`, `supplier_sku`, `unit_cost`, `currency`, `moq`, `units_per_box`, `lead_time_days`, `preferred`, notes, timestamps.

Unique/index strategy must prevent duplicate accidental relationships while allowing deliberate multiple supplier SKUs when required.

### `ssw_purchase_orders`

PO header: `id`, human-readable `po_number`, `supplier_id`, `location_id`, `status`, `currency`, `ordered_at`, `expected_at`, `shipped_at`, `received_at`, notes, created/updated user and timestamps.

### `ssw_purchase_order_items`

PO line: `id`, `purchase_order_id`, `product_id`, `supplier_product_id`, `ordered_qty`, `received_qty`, `unit_cost`, line metadata.

`remaining_qty` should normally be derived as ordered minus received rather than becoming an independently editable source of truth.

### `ssw_po_receipts`

Receipt event/header: `id`, `purchase_order_id`, `location_id`, `received_at`, `user_id`, note.

### `ssw_po_receipt_items`

Receipt lines: receipt ID, PO item ID, product ID, quantity received and related stock movement ID/reference.

### `ssw_locations`

Inventory locations: `id`, `name`, `code`, `active`, `sellable`, address/notes, sort order, timestamps.

Migration creates `Main warehouse` and seeds it from WooCommerce stock.

### `ssw_location_stock`

Current per-location balance for fast reads: `location_id`, `product_id`, `quantity`, `updated_at`.

The stock movement ledger is the audit truth for Woo Hoo Manager operations; this balance table is the efficient current-state projection.

### `ssw_stock_movements`

Immutable inventory ledger: `id`, `product_id`, `location_id`, `movement_type`, `quantity_before`, `delta`, `quantity_after`, `source_type`, `source_id`, `user_id`, `note`, `created_at`.

Movement types include manual adjustment, PO receipt, count correction, transfer out, transfer in, migration/import and Woo synchronization where needed.

### `ssw_barcodes`

`id`, `product_id`, `barcode`, `barcode_type`, `primary`, timestamps. Barcode value should be uniquely indexed unless a documented exception is required.

### `ssw_inventory_snapshots_daily`

Daily per-product/location inventory state for historical value and GMROI: date, product, location, quantity, unit cost snapshot, retail price snapshot, health score snapshot where useful.

### `ssw_product_metrics_daily`

Precomputed analytics per product/variation and date. Candidate fields:

- units_7d/30d/90d/365d;
- velocity_7d/30d/90d/365d;
- weighted_velocity;
- days_of_stock;
- projected_stockout_date;
- avg_realized_price;
- demand_mean/stddev/CV;
- ABC class;
- XYZ class;
- health score and component scores;
- forecast horizon quantities;
- forecast confidence/data-quality state;
- revenue/gross-profit contribution;
- last sale date;
- OOS duration/flags.

Do not over-denormalize every possible UI number. Store expensive/reused facts and derive cheap presentation values.

### `ssw_forecasts`

Forecast snapshots for accuracy measurement: `generated_for_date`, `product_id`, `horizon_date`, `forecast_qty`, `model_version`, `inputs_hash`, confidence metadata. Actuals are compared later against WooCommerce demand aggregation.

### `ssw_alerts`

Persisted/deduplicated operational alerts: `id`, `type`, `severity`, `product_id`, optional supplier/PO/location IDs, dedupe key, state, `first_seen_at`, `last_seen_at`, `snoozed_until`, `resolved_at`, JSON context payload.

### `ssw_reports`

Generated weekly report metadata and deterministic report payload/snapshot so a historical report does not change when current data changes. AI narrative, if enabled, is supplementary.

## WooCommerce aggregate stock synchronization

Multi-location stock introduces two representations:

1. Woo Hoo Manager per-location balances.
2. WooCommerce aggregate sellable stock.

For managed products:

`woocommerce_stock = sum(quantity at active sellable locations)`

A transfer between sellable locations creates equal opposite movements and therefore must not change aggregate Woo stock.

A PO receipt or adjustment changes a location balance and then synchronizes the aggregate through WooCommerce stock APIs.

The implementation must guard against recursion when WooCommerce stock hooks fire because Woo Hoo Manager itself performed the update.

## Reserved / available stock

Reserved stock is not a new editable balance. It is derived from eligible WooCommerce order states according to configurable rules compatible with WooCommerce stock-reduction behavior.

`available = on_hand - reserved`

The UI must make clear when reserved quantity is unavailable/unreliable due to store configuration.

## Analytics jobs

Use versioned jobs/batches:

1. historical order backfill;
2. daily sales aggregation;
3. daily inventory snapshot;
4. product metric calculation;
5. forecast generation;
6. alert evaluation;
7. weekly report generation.

Jobs must be idempotent or safely retryable.

## Data-quality states

Forecast-dependent metrics should expose one of at least:

- `insufficient_data`;
- `low_confidence`;
- `normal`;
- `high_confidence`.

The UI should prefer “not enough data yet” over a misleading exact forecast.

## Retention

Do not purge stock movements or PO receipts automatically. Forecast/metric snapshots can eventually use configurable retention/aggregation, but the first implementation should preserve enough history for year-over-year seasonality and auditability.