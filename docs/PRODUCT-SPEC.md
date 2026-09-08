# Woo Hoo Manager — Product Specification

**Status:** Approved-scope specification  
**Date:** 2026-09-08  
**Scope authority:** `docs/SCOPE-LOCK.md`

## 1. Product direction

Woo Hoo Manager extends the existing Stock Manager for WooCommerce inside the current WordPress/PHP/WooCommerce architecture. It should help a webshop manager understand stock/purchasing problems and act on the specific features the user selected. It is not a full ERP and this phase is not an architecture rewrite.

## 2. Existing behavior to preserve

Preserve the existing stock table, variations, low-stock thresholds, units-per-box, running-low/reorder workflow, CSV PO-style export, reorder emails, existing analytics, CSV/XLSX import/export, licensing/trial and HPOS compatibility unless an approved feature explicitly replaces a screen/workflow after parity is verified.

## 3. Architecture and safety

- Stay in the existing WordPress/PHP/WooCommerce plugin architecture.
- WooCommerce remains canonical for products, orders, prices and aggregate sellable stock.
- Custom WP database tables are allowed for approved features and their required history/ledger data.
- Heavy analytics run only in bounded background jobs, never normal storefront requests.
- Stock-changing operations require explicit user action, WooCommerce stock APIs and an audit movement.
- Migrations are versioned/retry-safe.
- Recommendations are advisory.
- Insufficient data must be visible; do not fabricate precision.
- AI is optional and may explain deterministic facts only.

## 4. Required internal analytics foundation

This is infrastructure, not an additional product feature. Approved risk/health/dead-stock/GMROI/planning/reporting features may use:

- historical units/revenue facts;
- daily inventory snapshots;
- 7/30/90-day velocity and a transparent weighted velocity;
- stock cover / days of stock;
- projected stockout;
- average realized selling price;
- last sale / days since sale;
- actual OOS duration where derivable;
- deterministic forecast quantities;
- confidence/data-quality state.

Canceled/failed/refunded quantities must not inflate demand. Use WooCommerce CRUD/refund APIs so HPOS remains supported.

## 5. Approved features

### 5.1 Supplier management
Supplier records plus product/variation supplier relationships. Support contact data, currency, lead time, MOQ/minimums, supplier SKU, purchase cost, pack/case size, notes and one preferred supplier per product.

### 5.2 Purchase Orders
Persistent POs with Draft, Approved, Ordered, Shipped, Partially received, Received and Cancelled states. Store supplier, dates/ETA, currency, notes, lines, costs, ordered/received/remaining quantities and totals. Partial receiving is first-class and receiving changes stock only after explicit confirmation. Preserve CSV export.

### 5.3 Revenue at Risk
Estimate financial exposure from a projected shortage using deterministic forecast, available/projected stock, incoming PO quantity, lead time and realized selling price. Show shortage units, horizon, assumptions and confidence next to the estimate.

### 5.4 Estimated Lost Sales from OOS — original selected item #9
Estimate missed demand only for actual out-of-stock periods. Base calculation may use baseline daily velocity × OOS duration and realized selling price. Always label the result **estimated**, never booked/accounting revenue.

### 5.5 Inventory Health Score
A 0–100 explainable score based only on approved inventory evidence such as availability/stockout risk, excess/aging, turnover/demand and margin/GMROI when cost exists. Expose component scores and renormalize when cost/history is unavailable.

### 5.6 Dead/slow-stock intelligence
Replace the simplistic unsold-60-days view with useful aging, last sale, velocity, stock quantity, tied-up cost/retail value and deterministic actions such as keep, reduce/stop reorder, markdown/clearance candidate or bundle candidate.

### 5.7 Evidence-based bundle recommendations
Recommend which bundles could make sense from actual order co-occurrence, especially slow/dead stock paired with historically compatible healthy/high-velocity products. Show evidence such as pair orders, support/attach rate and margin constraints where cost exists. Never auto-create a WooCommerce bundle/product.

### 5.8 GMROI
When purchase cost exists, calculate gross margin generated relative to average inventory cost using historical inventory snapshots. Mark results provisional when history is too short.

### 5.9 Barcode/mobile browser workflows
Mobile-friendly WordPress admin workflow with camera support where available and mandatory manual barcode fallback. Support product lookup and approved inventory operations such as PO receiving, stock adjustment/count assistance and location selection. No native app.

### 5.10 Multi-location / multi-warehouse
Create a default Main warehouse from Woo stock, keep per-location balances/movements, synchronize Woo sellable stock to configured sellable-location aggregate, support paired transfers that preserve aggregate quantity, target POs to a location and allow barcode operations by location.

### 5.11 Smart Alerts
Persist/dedupe operational alerts with severity/state/context and snooze/dismiss/resolve behavior. Alert conditions must come from approved capabilities, e.g. stockout risk, actual OOS, late PO, excess/dead stock, suspicious stock adjustment, low health or bundle opportunity. Do not turn anomaly ideas into unrelated standalone modules.

### 5.12 What Changed? dashboard
Show meaningful deltas/exceptions using approved data: revenue/orders/units where already available, newly OOS/at-risk items, incoming-stock changes, alerts, material inventory changes and other evidence needed by approved operations. Avoid decorative KPI walls.

### 5.13 Today Action Center
Prioritized actionable queue based on approved features. Every card must answer why now, estimated impact and what action can be taken, with a deep link to the relevant workflow.

### 5.14 Pareto analysis
Calculate actual store concentration rather than assuming an 80/20 rule. Show SKU contribution concentration and inventory capital held in low-contribution SKUs using available revenue/gross-profit/cost evidence.

### 5.15 90-day purchasing/cash forecast
Use deterministic demand, current stock, incoming POs, supplier lead time, MOQ/case size and purchase cost to show suggested order date/quantity, expected purchasing cash, supplier grouping, week/month totals and budget-pressure warnings. Never auto-place an order.

### 5.16 What-if simulator
Temporary, non-persistent scenario overrides for approved planning inputs such as demand percentage, supplier lead-time days, safety-stock days, reorder delay and optional budget cap. Compare baseline/scenario stockouts, Revenue at Risk, purchasing cash need and inventory level without changing production data.

### 5.17 Optional AI explanation layer
Only after deterministic evidence exists. AI may summarize anomalies/risks and generate an executive narrative from structured approved facts. It may not invent stock, cost, forecasts or orders and core functionality must work with AI disabled.

### 5.18 Weekly Executive Report
Scheduled WordPress/admin/email report using approved data: sales summary, inventory/risk, Revenue at Risk, Estimated Lost Sales, stockouts, dead/slow stock, open/incoming POs, health, top alerts/actions, bundle opportunities and 90-day purchasing/cash outlook. AI narrative is optional; report is complete without AI.

## 6. Final navigation consolidation

At the end, consolidate the approved capabilities into a clearer WordPress admin information architecture without rewriting the application architecture. Do not delete useful legacy screens until approved replacements reach parity.

## 7. Explicit exclusions

The following are not approved product features and must not be implemented or surfaced:

- ABC/XYZ segmentation.
- Product 360 as a separate feature/module.
- Seasonality-aware forecasting as a separate feature.
- Forecast-accuracy dashboard/module as a separate feature.
- Any SaaS/backend/React/external-DB/microservice rewrite.
- Native mobile app.
- Autonomous purchasing.
- AI-generated inventory truth/forecasting.
- Any feature not listed in `docs/SCOPE-LOCK.md`.

## 8. Performance and audit requirements

Use indexed custom tables, bounded/resumable jobs and precomputed/cached admin analytics. Every plugin-driven stock mutation records product/variation, location, before/delta/after, reason/type, source, user where applicable, timestamp and optional note. Recommendations expose enough evidence to explain why they exist.