# Woo Hoo Manager — Product Specification

**Status:** Approved-scope specification  
**Date:** 2026-09-08  
**Scope authority:** `docs/SCOPE-LOCK.md`

## 1. Product direction
Woo Hoo Manager extends the existing Stock Manager for WooCommerce inside the current WordPress/PHP/WooCommerce architecture. It is a practical inventory/purchasing intelligence layer for WooCommerce, not a SaaS rewrite or ERP.

## 2. Architecture and safety
- WooCommerce remains canonical for products, orders, prices and sellable stock.
- Heavy analytics run in bounded background jobs, never normal storefront requests.
- Stock mutations require explicit user action, Woo stock APIs and an audit movement.
- Migrations are versioned/retry-safe.
- Recommendations are advisory; no autonomous purchasing.
- Insufficient data is shown explicitly.
- AI is out of scope.

## 3. Required technical foundation
Approved features may use historical sales facts, daily inventory snapshots, 7/30/90-day velocity, weighted velocity, stock cover, projected stockout, realized selling price, OOS duration, forecast snapshots, supplier cost/lead-time/MOQ/pack inputs and incoming purchase quantities. These are infrastructure, not extra user-facing modules.

Supplier and incoming-purchase screens may remain only to provide data required by Smart Replenishment, Stockout Prediction and Cash Flow / Purchasing Forecast. One internal Main location and the immutable stock ledger may remain only to support Stock Adjustments, Stock Count and Anomaly Detection.

## 4. Approved features

### #2 Inventory Forecasting Engine
Deterministic forecast based on Woo sales history. Show current stock, demand velocity, days left, supplier lead time, incoming quantity and an actionable inventory status.

### #3 Days of Stock / Stock Cover
Show multiple demand windows and a weighted daily velocity. Core formula: available stock / average daily sales. Include estimated stockout date/coverage and explicit no-data states.

### #6 Smart Replenishment
Suggested order quantity uses deterministic demand during lead time + safety stock - available - incoming, then respects MOQ/pack-size rounding. Show boxes/units and resulting coverage. Never place an order automatically.

### #7 Stockout Prediction
Identify products expected to stock out before replenishment can arrive. Show projected stockout, earliest plausible delivery, inventory gap and potential lost units/revenue.

### #8 Revenue at Risk
Show estimated revenue exposed by projected shortage over a defined horizon, with shortage units and supporting assumptions.

### #9 Estimated Lost Sales from OOS
Use actual OOS duration and baseline velocity to estimate lost units/revenue. Always label values estimated.

### #10 Inventory Health Score
0–100 explainable score using approved inventory evidence such as stockout risk, excess/aging, demand/turnover and forecast coverage. No GMROI/ABC/Pareto dependencies.

### #12 Expanded Slow / Dead Stock
Statuses: Healthy, Slow, Very slow, Dead, Critical dead. Show stock, days since sale, tied-up cost/retail value where data exists and deterministic action suggestions such as stop reorder, markdown/clearance or bundle candidate.

### #17 Stock Adjustments
Support signed adjustments (+/-) with explicit reason and note. Every change is audited and synchronized to WooCommerce.

### #18 Stock Count / Inventura
Physical count workflow comparing expected vs counted quantity and applying the variance only after confirmation. Use one internal Main stock location; no multi-warehouse feature.

### #21 Bundles / Kits
Define component products and required quantities. Sellable bundle availability is limited by the scarcest component. Bundle/component stock behavior must remain consistent when Woo orders change bundle demand.

### Bundle Recommendations
Separately recommend which bundles to create based on real order co-occurrence, especially useful pairings for slow/dead stock. Show evidence and do not auto-create bundle products.

### #23 Smart Alerts
Persist/dedupe alerts for approved conditions such as stockout risk, actual OOS, late incoming purchase, excess/dead stock, anomalous movements and bundle opportunities. Support alert lifecycle actions.

### #24 Anomaly Detection
Detect abnormal sales spikes/drops, unexplained large stock movement, abnormal price change and sudden OOS spikes where reliable source history exists. Surface through Smart Alerts.

### #27 Product 360°
One product view combining approved data: price, stock, incoming, velocity, stock cover, projected stockout, recent revenue/units, supplier purchasing inputs, risk/lost-sales/health/dead-stock state and deterministic recommendation.

### #34 Seasonality-aware Forecasting
When sufficient history exists, use seasonal/month factors so exceptional periods such as Q4/BFCM do not corrupt ordinary baseline demand. Fall back to deterministic non-seasonal forecast when history is insufficient and expose confidence.

### #35 Forecast vs Actual
Compare saved historical forecast snapshots with realized unit sales at product and aggregate level. Show transparent error/accuracy metrics and insufficient-data states.

### #36 Cash Flow / Purchasing Forecast
Next-90-day purchasing plan grouped by month/supplier/currency. Show projected inventory spend, suggested order dates/quantities and projected revenue where supported by deterministic forecast/price data. Never mix currencies silently.

### #37 What-if Simulator
Temporary non-persistent scenarios such as sales +/- %, supplier lead-time change, safety stock and reorder delay/date. Compare stockouts, Revenue at Risk and required purchasing cash. Never mutate production stock/settings.

### #39 Weekly Executive Report
Deterministic weekly brief in plugin and optionally by email: revenue/orders/units, inventory value/risk, dead stock, stockout alerts, late incoming purchases, opportunities and 90-day purchasing/cash outlook. No AI narrative.

## 5. Explicit exclusions
Do not expose ABC/XYZ, GMROI, Barcode Scanner, Multi-Warehouse, Returns/Damaged state engine, What Changed, Today/Action Center, Category/Brand Intelligence, Pareto, Supplier Performance, Landed Cost, Price Intelligence, Promotion Impact, AI or external-channel integrations unless explicitly approved later.

## 6. Final navigation
After approved feature parity is reached, consolidate the WordPress admin information architecture so approved capabilities are easy to find and supporting-data screens are clearly secondary. Preserve WordPress/WooCommerce architecture.
