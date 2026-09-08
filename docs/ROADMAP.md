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
Infrastructure only for approved risk, health, dead-stock, GMROI and cash-planning features.
- Historical sales aggregation.
- Daily inventory snapshots.
- Bounded/resumable background jobs.

### Phase 6 — Required deterministic forecasting foundation
Infrastructure only.
- Demand velocity windows and weighted velocity.
- Stock cover and projected stockout.
- Forecast snapshots.
- Confidence/data-quality states.
- No automatic stock/PO action.

## Approved feature implementation

### Phase 7 — Selected inventory intelligence
- Revenue at Risk.
- Estimated Lost Sales from actual OOS periods.
- Inventory Health Score.
- Improved dead/slow-stock aging and tied-up capital.
- GMROI.
- Pareto analysis.
- Evidence-backed bundle recommendations.

### Phase 8 — Operations cockpit
- Smart Alerts with dedupe/snooze/resolve.
- What Changed? dashboard.
- Today Action Center.
- Prioritization/deep links only for approved workflows.

### Phase 9 — #36 Cash Flow / Purchasing Forecast
- 90-day purchasing/cash forecast.
- Monthly projected inventory spend (e.g. September/October/November).
- Supplier/week/month cash grouping.
- Suggested order dates/quantities using current stock, incoming POs, supplier lead time, MOQ/case size and deterministic demand.
- Projected revenue where supported by deterministic demand/price inputs.
- Budget-pressure warnings.
- Never automatically place orders.

### Phase 10 — #37 What-if simulator
- Temporary scenario inputs only; nothing persists to production stock/settings.
- Sales/demand increase or decrease.
- Supplier lead-time change.
- Reorder-delay/date scenarios.
- Compare stockouts, Revenue at Risk and required purchasing cash.

### Phase 11 — #39 Navigation consolidation
Only after approved replacement parity is verified, consolidate approved capabilities into the final information architecture requested by the user. Preserve the existing WordPress/WooCommerce architecture; this is navigation/UX consolidation, not an application rewrite.

## Explicitly removed / not approved
- ABC/XYZ segmentation.
- Product 360 as a separate feature/module.
- Seasonality-aware forecasting as a separate feature.
- Forecast-accuracy dashboard/module as a separate feature.
- Any AI feature or AI explanation layer.
- Weekly Executive Report.
- Any other feature outside `docs/SCOPE-LOCK.md`.

## Engineering constraints
- WooCommerce remains canonical for products/orders/aggregate sellable stock.
- No SaaS/backend rewrite, React SPA, external DB or microservices.
- No native mobile app.
- No autonomous purchasing.
- No storefront-heavy analytics.
- Stock mutations require explicit user action and audit movement.
- Existing plugin functionality remains unless an approved replacement reaches parity.