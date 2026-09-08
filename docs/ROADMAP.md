# Woo Hoo Manager — Roadmap

The roadmap is intentionally dependency-aware. Do not implement all features in one giant change.

## Phase 0 — Preserve and baseline

**Goal:** Import/preserve the current Stock Manager for WooCommerce plugin and establish a safe development baseline.

- Commit the existing plugin source unchanged as the baseline.
- Document current behavior and version.
- Add a minimal automated test/lint setup appropriate to the existing codebase without rewriting it.
- Verify activation, stock table, stock updates, low-stock screen, analytics, import/export, email scheduling, variations, license path and HPOS compatibility.
- Add database schema versioning/migration runner before introducing custom tables.

**Exit gate:** Existing features still work and a rollback point exists.

## Phase 1 — Operational data foundation

**Goal:** Build the trustworthy inventory ledger and master data needed by everything else.

- Suppliers and supplier-product relationships.
- Purchase cost, MOQ, case size and lead time.
- Locations with automatic `Main warehouse` migration.
- Location balances and immutable stock movement ledger.
- Manual stock adjustments with reason/note.
- Barcode mappings.
- Daily inventory snapshots.
- Historical order aggregation/backfill framework.

**Exit gate:** Aggregate Woo stock and location stock reconcile; every plugin-driven stock change is auditable.

## Phase 2 — Purchase Orders and receiving

**Goal:** Turn replenishment from CSV advice into a tracked operational workflow.

- PO headers/items/status workflow.
- Draft/approve/order/ship/partial receive/receive/cancel.
- Incoming quantities and ETA.
- Partial receipts.
- Explicit stock increment on receipt.
- Receiving into a selected location.
- Keep CSV export for interoperability.

**Exit gate:** A PO can be created, partially received and fully received without corrupting stock.

## Phase 3 — Barcode and multi-location workflows

**Goal:** Make warehouse operations usable from a phone/browser.

- Mobile-friendly scanner screen.
- Camera barcode scan + manual fallback.
- Product lookup.
- PO receiving by barcode.
- Stock adjustment by barcode.
- Stock count workflow.
- Location transfers.
- Aggregate Woo stock synchronization.

**Exit gate:** Scan/receive/adjust/count/transfer flows reconcile against the ledger.

## Phase 4 — Analytics foundation and forecasting

**Goal:** Build deterministic metrics once and reuse them everywhere.

- 7/30/90/365-day demand metrics.
- Weighted velocity.
- Stock cover/days of stock.
- Projected stockout date.
- Demand variability.
- Average realized selling price.
- Forecast snapshots and confidence/data-quality states.
- Scheduled incremental recalculation.
- Forecast-vs-actual storage and accuracy metrics.

**Exit gate:** Metrics are reproducible, explainable and do not require expensive live order scans.

## Phase 5 — Inventory intelligence

**Goal:** Turn metrics into product-level decisions.

- Revenue at Risk.
- Estimated Lost Sales from OOS.
- Inventory Health Score + history.
- ABC/XYZ segmentation.
- Dead/slow stock aging and tied-up capital.
- GMROI.
- Pareto analysis.
- Bundle opportunity recommendations from co-purchase data.
- Product 360 operational view combining stock, demand, suppliers, POs and intelligence.

**Exit gate:** Every recommendation exposes evidence/inputs and handles insufficient data safely.

## Phase 6 — Operations cockpit

**Goal:** Make Woo Hoo Manager useful every morning.

- Smart Alerts with dedupe/snooze/resolve.
- What Changed? dashboard.
- Today Action Center.
- Prioritization by severity, urgency and estimated impact.
- Deep links from actions to the relevant product/PO/stock workflow.

**Exit gate:** The dashboard answers what changed and what needs action without requiring manual report hunting.

## Phase 7 — Planning and scenarios

**Goal:** Help the manager plan inventory cash before problems happen.

- 90-day purchasing requirement forecast.
- Supplier/week/month cash grouping.
- Suggested order dates and quantities.
- Budget-pressure warnings.
- What-if simulator for demand, lead time, safety stock, delay and budget cap.
- Baseline-vs-scenario comparison.

**Exit gate:** Scenario changes never alter production settings or stock.

## Phase 8 — Executive reporting

**Goal:** Produce a concise management summary from deterministic data.

- Weekly report snapshot.
- Week-over-week comparisons.
- Inventory and risk summary.
- Purchasing/cash outlook.
- Top actions and opportunities.
- Configurable email delivery.

**Exit gate:** Report remains useful with AI completely disabled.

## Phase 9 — Optional AI layer

**Goal:** Explain structured intelligence, not replace it.

- Provider abstraction and settings.
- No committed API keys.
- Anomaly/risk explanation using structured metric payloads.
- Optional weekly executive narrative.
- Strict prompts that prohibit invented quantities/costs/orders.

Later candidate: conversational analytics (“Why did revenue drop?”) after the evidence APIs are stable.

## Phase 10 — UX consolidation

Once feature parity is proven, consolidate navigation toward:

`Dashboard / Inventory / Purchasing / Products / Analytics / Reports / Settings`

Remove or merge legacy screens only after replacements are verified.

## Explicit non-goals for this roadmap

- No external SaaS backend rewrite.
- No mandatory cloud database.
- No separate React application.
- No native iOS/Android warehouse app.
- No autonomous purchasing without confirmation.
- No LLM-generated stock forecasts replacing deterministic calculations.
- No full accounting/ERP system.

## Development strategy

Each phase should be a separately reviewable branch/PR where practical. Keep migrations backward-compatible, commit frequently, and never mix a broad architecture rewrite into a feature phase unless the approved spec is updated first.