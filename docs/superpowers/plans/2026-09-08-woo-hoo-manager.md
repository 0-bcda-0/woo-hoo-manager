# Woo Hoo Manager — Scope-Locked Implementation Plan

**Canonical scope:** `docs/SCOPE-LOCK.md`  
**Product spec:** `docs/PRODUCT-SPEC.md`  
**Architecture:** preserve the existing WordPress/PHP/WooCommerce modular plugin.

## Hard rule
Implement only user-approved features. Infrastructure is allowed only when required by an approved feature. Do not add ABC/XYZ, Product 360, seasonality-aware forecasting, forecast-accuracy UI or any other unapproved feature.

## Completed work
1. Baseline/test/migration foundation — PHP 7.4/8.1/8.3 gates, migrations, ZIP build.
2. Supplier management — CRUD, product mapping, cost/currency/MOQ/pack size/lead time/preferred supplier.
3. Multi-location stock foundation — Main warehouse, balances, immutable ledger, Woo aggregate sync/transfers.
4. Purchase Orders — persistent workflow, partial/full idempotent receiving, location-aware receipt.
5. Barcode workflows — mapping/lookup, mobile browser/manual fallback, adjustment/count/transfer operations.
6. Required analytics infrastructure — historical sales facts, daily inventory snapshots, bounded jobs.
7. Required deterministic forecast infrastructure — velocity, stock cover/projected stockout, forecast snapshots/confidence. This is infrastructure only.

## Remaining approved implementation

### 8. Revenue at Risk + Estimated Lost Sales + Health
- [ ] RED tests for Revenue at Risk and insufficient-data behavior.
- [ ] Implement Revenue at Risk with forecast/lead-time/incoming/price evidence.
- [ ] RED tests for actual-OOS Estimated Lost Sales (original selected #9).
- [ ] Implement estimated lost units/revenue with explicit estimated presentation contract.
- [ ] RED tests for Health Score components/missing-cost renormalization.
- [ ] Implement explainable Health Score and required history.

### 9. Dead/slow stock + GMROI + Pareto
- [ ] Test and implement aging, last-sale/velocity, tied-up capital and approved deterministic action labels.
- [ ] Test and implement GMROI with provisional state for short history.
- [ ] Test and implement actual Pareto concentration; never assume 80/20.

### 10. Bundle recommendations
- [ ] Test known co-purchase pairs/support/attach rate.
- [ ] Aggregate pair evidence in bounded jobs.
- [ ] Rank slow/dead-stock bundle candidates only when real affinity exists.
- [ ] Add margin/discount ceiling only with reliable cost.
- [ ] Show evidence; never auto-create products/bundles.

### 11. Smart Alerts
- [ ] Test dedupe/lifecycle.
- [ ] Persist open/snoozed/resolved/dismissed alerts.
- [ ] Evaluate only conditions derived from approved features.
- [ ] Scheduled bounded evaluation and deep links.

### 12. What Changed? + Today Action Center
- [ ] Test comparable-period/no-data behavior.
- [ ] Build deltas from approved facts/alerts.
- [ ] Deterministic priority with visible why/impact/action inputs.
- [ ] Action cards/deep links only for approved workflows.

### 13. 90-day purchasing/cash forecast
- [ ] Test order date/quantity from stock, incoming, lead time, MOQ, case size and cost.
- [ ] Supplier/week/month 90-day plan and cash requirements.
- [ ] Currency-safe totals and budget-pressure warnings.
- [ ] Draft-PO link only after explicit confirmation.

### 14. What-if simulator
- [ ] Prove overrides never persist to production.
- [ ] Demand %, lead-time days, safety-stock days, reorder delay, optional budget cap.
- [ ] Compare baseline/scenario stockouts, Revenue at Risk, cash need and inventory.

### 15. Weekly Executive Report
- [ ] Deterministic weekly payload and stable snapshot.
- [ ] Render only approved report sections.
- [ ] Configured email delivery without customer PII.

### 16. Optional AI explanation layer
- [ ] Only after deterministic approved evidence is stable.
- [ ] Structured evidence in, commentary out.
- [ ] Core plugin works with AI disabled.
- [ ] No invented stock/cost/order/forecast values.

### 17. Final navigation consolidation
- [ ] Consolidate only approved capabilities into requested final WordPress admin IA.
- [ ] Preserve architecture and legacy pages until parity.
- [ ] Full regression/security/performance pass and installable ZIP.

## Removed tasks
Do not implement ABC/XYZ segmentation, Product 360, seasonality-aware forecasting, forecast-accuracy dashboard/module, or any feature absent from `docs/SCOPE-LOCK.md`.

## Definition of done
Focused tests; PHP 7.4/8.1/8.3 compatibility; syntax/build gates; safe migrations; capabilities/nonces/sanitization/escaping; no storefront-heavy work; no implicit stock mutation; reviewed focused PR.