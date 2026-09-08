# Woo Hoo Manager — Product Specification

**Status:** Approved design baseline  
**Date:** 2026-09-08  
**Existing plugin:** Stock Manager for WooCommerce 3.3.0  
**Architecture decision:** Extend the existing WordPress/PHP/WooCommerce plugin. Do not move to an external SaaS/backend architecture in this project phase.

## 1. Vision

Woo Hoo Manager should become the daily WooCommerce operations cockpit for a webshop manager. It is not merely a prettier stock table and it is not intended to become a full ERP.

The main question the product answers is:

> What changed, what is likely to happen next, and what should I do about it today?

The UI should favor prioritized actions and explainable recommendations over passive charts.

## 2. Existing baseline that must remain working

The starting plugin already provides:

- WooCommerce stock table with inline editing, search and filters.
- Simple and variation product support.
- Per-product low-stock threshold.
- Running-low screen and threshold-based suggested reorder quantity.
- Units/pieces per box and box-rounded reorder quantities.
- CSV purchase-order-style export.
- Daily/weekly reorder email.
- Current vs previous month analytics for revenue, orders, units and AOV.
- Best sellers, daily revenue chart, retail stock value and products unsold for 60 days.
- CSV/XLSX stock import/export.
- Licensing/trial functionality.
- WooCommerce HPOS compatibility.

Existing behavior should be preserved unless this specification explicitly replaces it.

## 3. Architectural constraints

1. Stay inside the existing WordPress plugin architecture for now: PHP, WooCommerce APIs, WordPress admin, WP database and existing JS/CSS conventions.
2. Do not introduce a mandatory external SaaS service, external database, React SPA or separate application backend.
3. WooCommerce remains the canonical source for products, orders, prices and sellable stock.
4. Custom database tables are allowed where historical/time-series data cannot be modeled cleanly in post/product meta.
5. Never run expensive historical analytics synchronously on storefront requests.
6. Prefer scheduled/background aggregation using Action Scheduler when available through WooCommerce, with WP-Cron fallback only where appropriate.
7. All stock-changing operations must use WooCommerce stock APIs and create an audit record.
8. Existing installations must migrate forward without losing stock, thresholds, units-per-box, settings or licensing data.
9. Calculated recommendations are advisory unless the user explicitly confirms an action.
10. Every metric should degrade gracefully when historical data is insufficient; never present fabricated precision.

## 4. Internal analytics foundation

Several selected features require a shared forecasting layer even though forecasting is not a separate user-facing product module.

For each stock-managed product/variation calculate and persist/cache:

- units sold over 7, 30 and 90 days;
- average daily velocity over configurable windows;
- weighted velocity, initially favoring recent demand;
- days of stock / stock cover;
- projected stockout date;
- demand variability;
- weekly demand series;
- average realized selling price;
- revenue contribution;
- gross-profit contribution when cost data exists;
- last sale date;
- days since last sale;
- days out of stock;
- forecast quantity by future horizon;
- forecast confidence/data-quality flag.

Canceled, failed and refunded quantities must not inflate demand. Refund handling must use WooCommerce order/refund APIs rather than raw post assumptions so HPOS remains supported.

### Default weighted velocity

Initial deterministic model:

`weighted_daily_velocity = 0.60 * velocity_30d + 0.30 * velocity_90d + 0.10 * velocity_365d`

When a window lacks enough history, renormalize available weights instead of treating missing history as zero demand.

Seasonality-aware forecasting later augments this baseline; it does not delete the transparent baseline metric.

## 5. Selected product features

### 5.1 Supplier management

Create first-class supplier records and product-supplier relationships.

Supplier fields:

- name;
- contact person;
- email;
- phone;
- website;
- address/notes;
- default currency;
- default lead time in days;
- default minimum order value;
- active/inactive.

Per supplier-product relationship:

- WooCommerce product/variation ID;
- supplier SKU;
- purchase/unit cost;
- currency;
- MOQ;
- units per box/case;
- supplier-specific lead time override;
- preferred supplier flag;
- notes.

A product may have multiple suppliers, but at most one preferred supplier at a time.

### 5.2 Purchase Orders

Replace the current CSV-only concept with persistent purchase orders while retaining CSV export.

PO statuses:

`Draft -> Approved -> Ordered -> Shipped -> Partially received -> Received`

Also allow `Cancelled` without deleting history.

PO must store supplier, status, dates, expected arrival, currency, notes, line quantities, unit cost, ordered quantity, received quantity, remaining quantity and totals.

Receiving a PO can increment WooCommerce stock only after explicit user confirmation. Partial receipts are supported and recorded separately.

Inventory vocabulary exposed by the manager:

- **On hand:** physical stock currently recorded.
- **Reserved:** quantity reserved by eligible WooCommerce orders where reliably derivable.
- **Available:** on hand minus reserved.
- **Incoming:** unreceived quantity on active POs.
- **Projected:** available + incoming - forecast demand over selected horizon.

### 5.3 Revenue at Risk

Show products likely to become unavailable within the next 30 days and estimate revenue endangered by the projected shortage.

Base estimate:

`shortage_units = max(0, forecast_demand_until_replenishment - projected_available_stock)`

`revenue_at_risk = shortage_units * average_realized_selling_price`

Display the forecast horizon, assumed lead time, incoming PO quantity and confidence/data-quality flag next to the value.

### 5.4 Estimated Lost Sales from OOS

Estimate missed demand while a product was actually out of stock.

`estimated_lost_units = baseline_daily_velocity * out_of_stock_duration_days`

`estimated_lost_revenue = estimated_lost_units * average_realized_selling_price`

Label these values explicitly as **estimated**, never as booked/accounting revenue.

### 5.5 Inventory Health Score

Score each SKU/variation from 0 to 100 with visible sub-scores. Initial weights:

- 35% availability / stockout risk;
- 25% excess stock and aging;
- 20% turnover/demand health;
- 20% margin/GMROI quality when cost exists.

If cost data is unavailable, renormalize remaining components and display that margin data is missing.

Statuses:

- 80–100 Healthy
- 60–79 Watch
- 40–59 At Risk
- 0–39 Critical

Store historical score snapshots so trend can be displayed.

### 5.6 ABC/XYZ segmentation

ABC defaults to gross-profit contribution when reliable cost exists, otherwise revenue contribution.

Suggested cumulative thresholds:

- A: first 80% of contribution;
- B: next 15%;
- C: final 5%.

XYZ is based on weekly demand variability over a rolling 12-week minimum window using coefficient of variation where meaningful:

- X: stable;
- Y: variable;
- Z: highly intermittent/unstable.

Surface combinations such as AX as operationally critical and CZ as potential discontinue/clearance candidates. Thresholds must be settings, not magic constants hidden in code.

### 5.7 Dead/slow stock intelligence and bundle recommendations

Replace the single `unsold 60 days` view with aging buckets:

- 0–30 days;
- 31–60;
- 61–90;
- 91–180;
- 180+.

For each product show units, inventory cost tied up, retail value, last sale, velocity and recommended action.

Possible deterministic actions:

- keep;
- reduce reorder;
- stop reorder;
- markdown candidate;
- clearance candidate;
- bundle candidate.

#### Bundle recommendations

Generate bundle opportunities from actual order co-occurrence rather than arbitrary AI text. Prioritize:

1. slow/dead SKU + frequently co-purchased healthy SKU;
2. slow/dead SKU + high-velocity bestseller where historical affinity exists;
3. complementary products with meaningful support/confidence.

Display the evidence: orders containing both, support, confidence/attach rate, current margins if known, and a suggested maximum discount that does not cross a configurable gross-margin floor.

A recommendation does **not** automatically create a WooCommerce bundle/product.

### 5.8 GMROI

When purchase cost exists:

`GMROI = gross_margin_generated / average_inventory_cost`

Use historical inventory snapshots for average inventory cost. Until enough history exists, mark GMROI as provisional and disclose the shorter measurement window/current-inventory fallback.

### 5.9 Barcode scanner

Provide a mobile-friendly WordPress admin workflow for barcode-driven operations:

- find a product/variation;
- receive PO items;
- perform a stock adjustment;
- assist stock count.

Use browser camera APIs where supported, with manual barcode entry as mandatory fallback. Barcode mappings must support GTIN/EAN/UPC values without assuming SKU equals barcode.

No separate native mobile app is required.

### 5.10 Multi-warehouse foundation

Support multiple inventory locations in the data model while keeping WooCommerce compatible.

- On migration create a default `Main warehouse`.
- Seed its quantity from current WooCommerce stock.
- Store per-location balances/movements in plugin tables.
- WooCommerce sellable stock remains synchronized to the configured aggregate of sellable locations.
- Location transfers create paired stock movements and must not alter aggregate total stock.
- POs can target a location.
- Barcode receiving/adjustment/counting can choose a location.

This is a modular-monolith feature inside WordPress, not a separate warehouse service.

### 5.11 Smart Alerts

Create persisted, deduplicated alerts with severity:

- Critical;
- Warning;
- Opportunity.

Initial alert types include:

- predicted stockout before replenishment;
- actual stockout;
- PO overdue/late;
- demand velocity spike/drop;
- excess stock;
- dead-stock escalation;
- unusually large stock adjustment;
- low Inventory Health Score;
- forecast error deterioration;
- bundle opportunity.

Alerts need first_seen, last_seen, state, context payload, dismiss/snooze/resolve semantics and deep links to the relevant action.

### 5.12 What Changed? morning dashboard

Dashboard comparison against the previous comparable period/day:

- revenue;
- orders;
- units;
- newly out-of-stock SKUs;
- newly low-stock/at-risk SKUs;
- incoming stock changes;
- new/changed alerts;
- biggest demand anomalies;
- largest inventory-value changes.

Focus on deltas and exceptions, not a wall of KPIs.

### 5.13 Today Action Center

The default dashboard should contain a prioritized action queue.

Example tasks:

- reorder an at-risk SKU;
- review late PO;
- receive incoming PO;
- investigate demand spike;
- count/verify suspicious stock;
- review dead-stock/bundle opportunity;
- add missing supplier/cost data to a high-value SKU.

Every action card must answer: **why now?**, **estimated impact**, and **what action can I take?**

### 5.14 Pareto analysis

Calculate and display concentration, e.g.:

- percentage of SKUs generating 80% of revenue/gross profit;
- percentage of inventory capital held in low-contribution SKUs;
- cumulative contribution curve/table.

Never hard-code a marketing claim such as “20% of products make 80% of revenue”; calculate the actual store values.

### 5.15 90-day purchasing/cash forecast

Forecast expected purchasing requirements by week for the next 90 days using demand forecast, current stock, incoming PO stock, supplier lead time, MOQ, case size and purchase cost.

Show:

- suggested order date;
- suggested quantity;
- expected cash requirement;
- supplier grouping;
- week/month totals;
- budget pressure warnings.

Do not automatically place orders.

### 5.16 What-if simulator

Allow temporary scenario overrides without changing production settings:

- demand +/- percentage;
- supplier lead time +/- days;
- safety-stock days;
- reorder delay;
- optional purchasing budget cap.

Compare baseline vs scenario for stockouts, revenue at risk, purchasing cash need and inventory level.

Scenario calculations can be transient/session-based; persistent scenario history is not required initially.

### 5.17 AI-assisted intelligence

AI is a presentation/analysis layer after deterministic metrics exist.

Allowed first uses:

- summarize important anomalies;
- explain why a metric changed using supplied structured evidence;
- summarize forecast/replenishment risks;
- generate an executive narrative.

Core forecasts, health scores and reorder math must work with AI disabled. Never let an LLM invent stock quantities, costs or orders. Provider integration must be optional and abstracted behind an interface/settings layer; no secret key may be committed.

A conversational “why did revenue drop?” interface is a later extension, not required for the first AI milestone.

### 5.18 Weekly Executive Report

Generate a scheduled weekly report in WordPress and optionally email it to configured recipients.

Include:

- revenue, gross profit when available, orders and units;
- comparison with prior week;
- inventory retail and cost value;
- revenue at risk;
- estimated lost sales;
- stockouts and near-stockouts;
- dead/slow stock and tied-up capital;
- incoming/open PO summary;
- Inventory Health trend;
- top alerts/actions;
- opportunities including bundle candidates;
- 90-day purchasing/cash outlook.

AI narrative may be appended when enabled, but the report must be complete without AI.

## 6. Information architecture

Target top-level product navigation:

- **Dashboard** — What Changed + Today Action Center + critical overview.
- **Inventory** — stock, locations, adjustments, counts, low/at-risk, scanner.
- **Purchasing** — suppliers, purchase orders, receiving, purchasing forecast.
- **Products** — Product 360 and product-level operational settings.
- **Analytics** — demand, health, ABC/XYZ, Pareto, dead stock, GMROI, forecast accuracy.
- **Reports** — weekly executive reports and exports.
- **Settings** — forecasting defaults, thresholds, locations, alerts, email, AI/provider and existing license/settings.

Do not delete useful existing screens before their replacement reaches parity.

## 7. Performance requirements

The current implementation has small-store assumptions (for example capped product/order analytics). The new analytics layer must not simply remove caps and run giant synchronous WooCommerce queries.

Use incremental/scheduled aggregation and indexed custom tables for time-series metrics. Admin pages should read precomputed data where possible. Batch migrations/backfills and show progress/state rather than timing out.

Target behavior:

- no analytics work on normal storefront page loads;
- bounded batch sizes;
- resumable backfills;
- indexed product/date/status columns;
- cached dashboard/report queries;
- invalidation or refresh jobs after meaningful stock/order changes.

## 8. Auditability and safety

Any stock mutation performed by Woo Hoo Manager records:

- product/variation;
- location;
- quantity before;
- delta;
- quantity after;
- reason/type;
- source entity such as PO/count/transfer/manual;
- user ID where applicable;
- timestamp;
- optional note.

Analytics calculations should store or expose enough inputs that a manager can understand why a recommendation exists.

## 9. Definition of success

The feature set is successful when a manager can open Woo Hoo Manager in the morning and, without exporting spreadsheets, understand:

1. what materially changed;
2. which products are likely to stock out and what revenue is at risk;
3. what to reorder and approximately when;
4. which inventory is tying up cash;
5. which POs/suppliers require attention;
6. what actions are most important today;
7. how much purchasing cash is likely to be needed over the next 90 days;
8. whether forecasts/recommendations are becoming more or less reliable.