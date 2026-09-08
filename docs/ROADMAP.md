# Woo Hoo Manager — Approved Roadmap

> `docs/SCOPE-LOCK.md` is canonical. Original feature numbering from the user's source list is authoritative.

The existing WordPress/PHP/WooCommerce architecture stays in place.

## Completed or largely implemented

- **#2 Inventory Forecasting Engine** — deterministic demand/forecast foundation exists.
- **#3 Days of Stock / Stock Cover** — implemented from weighted velocity and current stock.
- **#6 Smart Replenishment** — deterministic suggested order quantities use stock, incoming, lead time, safety stock and pack/MOQ inputs.
- **#7 Stockout Prediction** — projected stockout date/coverage foundation exists.
- **#8 Revenue at Risk** — implemented.
- **#9 Estimated Lost Sales from OOS** — implemented and explicitly labeled estimated.
- **#10 Inventory Health Score** — implemented from approved inventory components.
- **#12 Slow / Dead Stock** — aging/status foundation implemented.
- **#17 Stock Adjustments** — focused Stock Operations workflow.
- **#18 Stock Count / Inventura** — focused Stock Operations count workflow.
- **Bundle Recommendations** — evidence-based co-purchase recommendations implemented.
- **#23 Smart Alerts** — persisted/deduped alert lifecycle implemented.
- **#36 Cash Flow / Purchasing Forecast** — deterministic 90-day purchasing/cash planning implemented.
- **#37 What-if Simulator** — non-persistent scenario engine implemented.

## Supporting infrastructure only

Supplier/cost/lead-time/MOQ inputs, incoming-purchase/receiving data, one internal Main stock location, immutable movement ledger, historical sales facts, daily snapshots and forecast caches may remain only because approved features depend on them. They must not expand into unrelated product modules.

## Remaining approved work

### #21 Bundles / Kits
- Define bundle components and required quantities.
- Calculate sellable bundle availability as the limiting component quantity.
- When bundle stock changes through Woo orders, reconcile component availability safely.
- Keep bundle recommendations as a separate evidence layer.

### #24 Anomaly Detection
- Detect abnormal sales velocity spikes/drops.
- Detect large stock movements without corresponding expected source context.
- Detect unusual price changes where Woo history supports it.
- Detect sudden OOS spikes.
- Surface anomalies through Smart Alerts; do not create a generic AI system.

### #27 Product 360°
One product page combining approved evidence only: price, stock, incoming, velocity, stock cover, projected stockout, revenue/units, supplier purchasing inputs, risk/lost-sales/health/dead-stock state and deterministic recommendation.

### #34 Seasonality-aware Forecasting
- Use longer historical periods and month/season factors when enough history exists.
- Prevent Q4/BFCM-like spikes from contaminating ordinary baseline demand.
- Keep a deterministic fallback when history is insufficient.
- Expose confidence/data sufficiency.

### #35 Forecast vs Actual
- Persist comparable forecast snapshots.
- Compare predicted vs realized units by product and aggregate period.
- Show transparent error/accuracy metrics and low-data states.

### #39 Weekly Executive Report
- Deterministic weekly report and historical snapshot.
- Revenue/orders/units plus approved inventory-risk, alerts, dead stock, cash forecast and opportunities.
- Email delivery and in-plugin view.
- No AI narrative.

## Final UX consolidation
After the approved feature set reaches parity, clean up the WordPress admin navigation so only approved product capabilities plus necessary supporting-data screens remain visible. This is UX/navigation consolidation, not an architecture rewrite.

## Explicitly not approved
ABC/XYZ, GMROI, Barcode Scanner, Multi-Warehouse, What Changed, Today/Action Center, Pareto, Category/Brand Intelligence, Supplier Performance, Landed Cost, Price Intelligence, Promotion Impact, AI and external-channel integrations are out of scope unless the user later explicitly approves them.
