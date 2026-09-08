# Woo Hoo Manager — Approved Roadmap

> `docs/SCOPE-LOCK.md` is the canonical scope authority. No feature may be added to this roadmap unless the user explicitly approves it.

The existing WordPress/PHP/WooCommerce architecture stays in place. This roadmap is dependency-aware, but technical dependencies are not separate product features.

## Completed foundation

### Phase 0 — Preserve, tests and migrations
- Preserve existing plugin behavior.
- PHP 7.4/8.1/8.3 test/syntax gates.
- Versioned retry-safe DB migrations.
- Installable WordPress ZIP build.

### Phase 1 — Suppliers
- Supplier CRUD.
- Product-supplier relationships.
- Cost, currency, MOQ, pack size, lead time and preferred supplier.

### Phase 2 — Multi-location stock foundation
- Main warehouse migration.
- Location balances.
- Immutable stock movement ledger.
- Manual adjustments and Woo aggregate reconciliation.

### Phase 3 — Purchase Orders
- Persistent PO headers/items/statuses.
- Partial/full receiving.
- Idempotent receipt handling.
- Receiving into selected warehouse/location.

### Phase 4 — Barcode workflows
- Barcode mapping/lookup.
- Mobile-friendly browser workflow with manual fallback.
- Stock adjustment/count/transfer operations backed by the stock ledger.

### Phase 5 — Required analytics foundation
This is infrastructure for approved risk, health, dead-stock, GMROI, purchasing forecast and reporting features; it is not a separate user-facing feature.
- Historical sales aggregation.
- Daily inventory snapshots.
- Bounded/resumable background jobs.

### Phase 6 — Required deterministic forecasting foundation
This is infrastructure only.
- Demand velocity windows and weighted velocity.
- Stock cover and projected stockout.
- Forecast snapshots.
- Confidence/data-quality states.
- No AI forecasting and no automatic stock/PO action.

## Next approved work

### Phase 7 — Selected inventory intelligence
Implement only:
- Revenue at Risk.
- Estimated Lost Sales from actual OOS periods.
- Inventory Health Score.
- Improved dead/slow-stock aging and tied-up capital.
- GMROI.
- Pareto analysis.
- Evidence-backed bundle recommendations.

**Explicit exclusion:** no ABC/XYZ segmentation.

### Phase 8 — Operations cockpit
- Smart Alerts with dedupe/snooze/resolve.
- What Changed? dashboard.
- Today Action Center.
- Prioritization and deep links only as needed by those approved features.

### Phase 9 — 90-day planning
- 90-day purchasing/cash forecast.
- Supplier/week/month cash grouping.
- Suggested order dates/quantities using existing deterministic inputs.
- Budget-pressure warnings.
- What-if simulator for approved scenario inputs.
- Never automatically place orders.

### Phase 10 — Weekly Executive Report
- Deterministic weekly report.
- Week-over-week comparisons.
- Inventory/risk/purchasing summaries drawn only from approved features.
- Configurable email delivery.

### Phase 11 — Optional AI explanation layer
- Only after deterministic evidence exists.
- Explanation/summarization of approved metrics and report facts.
- AI never invents quantities, costs, forecasts or orders.
- No AI forecast engine.

### Phase 12 — Navigation consolidation
Only after replacement parity is verified, consolidate the approved capabilities into the final information architecture requested by the user. Keep the existing WordPress/WooCommerce architecture; this is navigation/UX consolidation, not an application rewrite.

## Removed from active roadmap

The following are not approved and must not be implemented:
- ABC/XYZ segmentation.
- Product 360 as a separate feature/module.
- Seasonality-aware forecasting as a separate feature.
- Forecast-accuracy dashboard/module as a separate feature.
- Any other feature outside `docs/SCOPE-LOCK.md`.

## Engineering constraints
- WooCommerce remains canonical for products/orders/aggregate sellable stock.
- No SaaS/backend rewrite, React SPA, external DB or microservices.
- No native mobile app.
- No autonomous purchasing.
- No storefront-heavy analytics.
- Stock mutations require explicit user action and audit movement.
- Existing plugin functionality remains unless an approved replacement reaches parity.