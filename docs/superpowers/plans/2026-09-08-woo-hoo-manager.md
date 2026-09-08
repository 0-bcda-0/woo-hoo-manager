# Woo Hoo Manager Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Evolve the existing Stock Manager for WooCommerce plugin into Woo Hoo Manager, an explainable inventory, purchasing and operations cockpit, without replacing its WordPress/WooCommerce architecture.

**Architecture:** Preserve the existing plugin as a modular WordPress/PHP application. Add focused domain classes and versioned custom tables for relational/history-heavy capabilities, while WooCommerce remains canonical for products, orders and aggregate sellable stock. Heavy analytics are incrementally precomputed in background jobs and consumed by admin UI/reporting.

**Tech Stack:** PHP, WordPress, WooCommerce CRUD/stock APIs, WooCommerce HPOS, `$wpdb`, WooCommerce Action Scheduler where available, WordPress admin JS/CSS, browser camera/barcode APIs with manual fallback.

**Spec:** `docs/PRODUCT-SPEC.md` and `docs/DATA-MODEL.md`

## Global Constraints

- Keep the current WordPress/PHP/WooCommerce architecture; no external SaaS/backend rewrite.
- Preserve existing plugin functionality and existing installation data.
- WooCommerce remains canonical for products, orders and aggregate sellable stock.
- Maintain simple-product, variation and HPOS compatibility.
- Never change stock from a forecast/recommendation/AI response without explicit user action.
- Every Woo Hoo Manager stock mutation creates an immutable audit movement.
- Expensive analytics run in bounded background batches, not storefront requests.
- Forecast-dependent outputs expose data-quality/confidence state.
- AI is optional and never becomes the source of stock/cost/order truth.
- Admin writes require capabilities, nonces, sanitization, escaped output and prepared SQL.

---

## Project file map

The existing plugin files remain authoritative until imported. Do not rename them merely to match this plan. New responsibilities should be introduced as focused classes under the plugin's existing `includes/` convention where possible.

Expected new logical components (exact filenames may follow established repository naming after import):

- `includes/class-ssw-db.php` — schema version and migrations.
- `includes/class-ssw-stock-ledger.php` — movements and location reconciliation.
- `includes/class-ssw-suppliers.php` — supplier domain/service.
- `includes/class-ssw-purchase-orders.php` — PO state and line calculations.
- `includes/class-ssw-receiving.php` — idempotent receipt workflow.
- `includes/class-ssw-locations.php` — location balances/transfers/Woo aggregate sync.
- `includes/class-ssw-barcodes.php` — barcode mapping and lookup.
- `includes/class-ssw-demand-metrics.php` — order aggregation and velocity.
- `includes/class-ssw-forecast.php` — deterministic forecast and confidence.
- `includes/class-ssw-inventory-intelligence.php` — risk/health/ABC-XYZ/GMROI/Pareto.
- `includes/class-ssw-bundle-opportunities.php` — co-purchase analysis.
- `includes/class-ssw-alerts.php` — alert evaluation/lifecycle.
- `includes/class-ssw-action-center.php` — prioritized operational tasks.
- `includes/class-ssw-planning.php` — 90-day cash/replenishment scenarios.
- `includes/class-ssw-reports.php` — weekly report snapshots/delivery.
- `includes/class-ssw-ai.php` or provider interface — optional explanation layer only when Phase 9 is reached.

Do not create all classes empty in advance. Create them with the task that first needs them.

### Task 1: Import and freeze the current plugin baseline

**Files:**
- Add: existing plugin source from the approved uploaded ZIP
- Create/Modify: root/plugin README only as needed to distinguish product docs from plugin runtime files

**Interfaces:**
- Consumes: existing Stock Manager for WooCommerce 3.3.0 source.
- Produces: an exact, reviewable baseline from which all later tasks branch.

- [ ] **Step 1: Import the plugin without feature changes.** Preserve its current directory structure and PHP bootstrap.
- [ ] **Step 2: Run PHP syntax checks over every PHP file.** Example: `find . -name '*.php' -print0 | xargs -0 -n1 php -l` and require zero syntax failures.
- [ ] **Step 3: Inventory existing hooks, options/meta keys, AJAX actions, scheduled events and licensing behavior in a short `docs/BASELINE.md`.** Include the existing 500-product/2,000-order analytics limitations so they are not accidentally treated as future architecture.
- [ ] **Step 4: Verify no source file changed relative to the supplied baseline except documentation/repository metadata.**
- [ ] **Step 5: Commit:** `git commit -m "chore: import Stock Manager for WooCommerce baseline"`.

### Task 2: Establish test and migration foundations

**Files:**
- Create/Modify: test/bootstrap configuration following the plugin's existing conventions.
- Create: migration/schema service only when tests can exercise it.

**Interfaces:**
- Produces: `SSW_DB::maybe_upgrade()` (or equivalent existing-prefix naming) and a stored schema version.

- [ ] **Step 1: Add a minimal WordPress/WooCommerce-compatible test harness or, if repository execution environment cannot run WP integration tests, deterministic unit tests for pure services plus documented integration smoke checks.**
- [ ] **Step 2: Write a failing migration test proving activation/upgrade is repeatable and does not destroy existing plugin options/meta.**
- [ ] **Step 3: Implement schema versioning with idempotent `dbDelta()` migrations and a single schema-version option.**
- [ ] **Step 4: Re-run migration twice and assert the second run is a no-op with intact data.**
- [ ] **Step 5: Run syntax/test suite and commit:** `feat: add versioned database migrations`.

### Task 3: Suppliers and product purchasing metadata

**Files:**
- Create: supplier service/repository and admin UI files following current admin patterns.
- Modify: bootstrap/admin navigation.
- Test: supplier CRUD/preferred-supplier behavior.

**Interfaces:**
- Produces supplier CRUD and a product-supplier relation returning cost, currency, MOQ, units-per-box and lead time.

- [ ] **Step 1: Write failing tests for creating a supplier, assigning two suppliers to one variation and enforcing one preferred supplier.**
- [ ] **Step 2: Add `ssw_suppliers` and `ssw_supplier_products` migration with product/supplier indexes.**
- [ ] **Step 3: Implement sanitized CRUD with capability/nonces and decimal-safe cost handling.**
- [ ] **Step 4: Add supplier/product relationship UI without removing existing units-per-box behavior; migrate/fallback existing box data where appropriate.**
- [ ] **Step 5: Verify simple products and variations and commit:** `feat: add supplier purchasing data`.

### Task 4: Locations and immutable stock ledger

**Interfaces:**
- Produces operations equivalent to `record_movement(product_id, location_id, delta, type, source, note)` and `sync_woocommerce_aggregate(product_id)`.

- [ ] **Step 1: Write failing tests for default-location migration, adjustment audit and aggregate reconciliation.**
- [ ] **Step 2: Migrate `ssw_locations`, `ssw_location_stock`, `ssw_stock_movements`; create `Main warehouse`.**
- [ ] **Step 3: Seed managed product/variation quantities from WooCommerce exactly once and record migration provenance.**
- [ ] **Step 4: Implement manual adjustment using WooCommerce stock APIs plus ledger entry and recursion guard.**
- [ ] **Step 5: Test positive/negative adjustment, variation stock and retry safety; commit:** `feat: add location stock ledger`.

### Task 5: Purchase Orders and idempotent receiving

**Interfaces:**
- Produces PO create/update/status operations and a receipt operation that cannot double-apply stock.

- [ ] **Step 1: Write failing state-transition tests for Draft through Received plus Cancelled and partial receipt.**
- [ ] **Step 2: Add PO, item, receipt and receipt-item tables/indexes.**
- [ ] **Step 3: Implement totals, remaining quantities and valid transitions.**
- [ ] **Step 4: Implement partial receipt into a location; one receipt transaction creates stock movements and synchronizes Woo aggregate once.**
- [ ] **Step 5: Retry the same receipt request in a test and prove stock is not incremented twice.**
- [ ] **Step 6: Preserve CSV export and add PO/admin screens. Commit:** `feat: add purchase order workflow`.

### Task 6: Barcode, count and transfer workflows

**Interfaces:**
- Produces barcode lookup, count correction and paired transfer movements.

- [ ] **Step 1: Add barcode table and tests for unique lookup/manual fallback.**
- [ ] **Step 2: Implement mobile-friendly scanner UI using supported browser camera APIs with a manual text field always available.**
- [ ] **Step 3: Wire scanner to product lookup and PO receiving.**
- [ ] **Step 4: Add stock-count session/workflow that calculates variance and requires confirmation before posting correction movement.**
- [ ] **Step 5: Add transfer operation with equal `transfer_out`/`transfer_in` movements and assert aggregate Woo stock is unchanged.**
- [ ] **Step 6: Commit:** `feat: add barcode inventory workflows`.

### Task 7: Historical aggregation and daily snapshots

**Interfaces:**
- Produces per-product daily sales facts and inventory snapshots consumed by forecasting/GMROI.

- [ ] **Step 1: Write fixtures/tests covering completed/processing sales, refunds, cancellations and variations through WooCommerce APIs.**
- [ ] **Step 2: Add snapshot/metric tables and indexes.**
- [ ] **Step 3: Implement bounded, resumable historical backfill with cursor/checkpoint state.**
- [ ] **Step 4: Implement daily inventory snapshot including quantity, cost and retail-price context.**
- [ ] **Step 5: Prove rerunning a batch/day does not duplicate facts. Commit:** `feat: add inventory analytics aggregation`.

### Task 8: Deterministic demand metrics and forecasts

**Interfaces:**
- Produces velocity windows, weighted velocity, stock cover, projected stockout, demand variability, forecast snapshots and confidence state.

- [ ] **Step 1: Write pure calculation tests for weighted velocity including missing-window weight renormalization.**
- [ ] **Step 2: Test zero demand, intermittent demand, new SKU, refund-heavy history and incoming-stock cases.**
- [ ] **Step 3: Implement shared calculation service; do not duplicate formulas in UI code.**
- [ ] **Step 4: Persist daily metrics/forecast snapshots in scheduled bounded jobs.**
- [ ] **Step 5: Add forecast-vs-actual comparison/accuracy storage.**
- [ ] **Step 6: Verify storefront requests do not trigger backfills. Commit:** `feat: add demand forecasting foundation`.

### Task 9: Inventory intelligence metrics

**Interfaces:**
- Consumes Task 7/8 metrics and supplier costs.
- Produces Revenue at Risk, lost-sales estimate, health score/components, ABC/XYZ, aging, GMROI and Pareto facts.

- [ ] **Step 1: Write formula tests using explicit numeric fixtures for each metric and insufficient-cost/history cases.**
- [ ] **Step 2: Implement Revenue at Risk with shortage units, horizon, price and incoming-stock evidence.**
- [ ] **Step 3: Implement estimated OOS lost units/revenue and label it estimated at the presentation boundary.**
- [ ] **Step 4: Implement Health Score with component weights and renormalization when margin data is missing.**
- [ ] **Step 5: Implement ABC by gross profit with revenue fallback and configurable cumulative thresholds; implement XYZ from weekly variability.**
- [ ] **Step 6: Implement aging buckets/tied-up capital and GMROI with provisional state.**
- [ ] **Step 7: Implement actual-store Pareto concentration. Commit:** `feat: add inventory intelligence metrics`.

### Task 10: Bundle opportunity engine

**Interfaces:**
- Produces evidence-backed product-pair recommendations; never auto-creates products.

- [ ] **Step 1: Write fixtures with known co-purchase pairs and assert support/attach-rate ordering.**
- [ ] **Step 2: Aggregate pair co-occurrence in bounded jobs rather than scanning all orders on page load.**
- [ ] **Step 3: Rank slow/dead SKU pairings with healthy/high-velocity products only when affinity evidence clears configurable thresholds.**
- [ ] **Step 4: Calculate suggested discount ceiling from available cost/margin and configured gross-margin floor; omit discount advice when cost is missing.**
- [ ] **Step 5: Present evidence and action recommendation. Commit:** `feat: recommend inventory bundle opportunities`.

### Task 11: Smart alerts and anomaly evaluation

**Interfaces:**
- Produces deduplicated persisted alerts with severity/state/context/deep link.

- [ ] **Step 1: Test repeated evaluation of the same condition updates one alert instead of spamming duplicates.**
- [ ] **Step 2: Implement alert table/repository lifecycle: open, snoozed, resolved/dismissed.**
- [ ] **Step 3: Implement initial deterministic alert evaluators from the product spec.**
- [ ] **Step 4: Implement velocity anomaly detection from recent baseline with minimum-data guards.**
- [ ] **Step 5: Run evaluation as a scheduled job and commit:** `feat: add smart inventory alerts`.

### Task 12: What Changed and Today Action Center

**Interfaces:**
- Produces dashboard delta DTOs and prioritized action items with why/impact/action/deep-link fields.

- [ ] **Step 1: Test comparison-period calculations across normal days and no-data days.**
- [ ] **Step 2: Implement What Changed deltas from precomputed facts/alerts.**
- [ ] **Step 3: Define deterministic priority scoring using severity, urgency and estimated financial/stock impact; expose score inputs.**
- [ ] **Step 4: Implement action cards and direct operations links.**
- [ ] **Step 5: Make this the target Dashboard while retaining legacy screens until parity. Commit:** `feat: add operations action center`.

### Task 13: Product 360 and analytics navigation

- [ ] **Step 1: Build a product operational view combining current stock/location, velocity/forecast, supplier/cost, open POs, health, alerts, aging and relevant recommendations.**
- [ ] **Step 2: Add Analytics screens for ABC/XYZ, Pareto, dead stock, GMROI and forecast accuracy using precomputed facts.**
- [ ] **Step 3: Add filtering/export where it reuses existing export patterns.**
- [ ] **Step 4: Verify query counts/bounded pagination on large synthetic datasets. Commit:** `feat: add product 360 analytics`.

### Task 14: Seasonality-aware forecasting

- [ ] **Step 1: Define/test seasonality eligibility: insufficient history falls back to transparent weighted velocity.**
- [ ] **Step 2: Add weekly/monthly seasonal factors derived only from store history with outlier/minimum-sample guards.**
- [ ] **Step 3: Store model version and inputs with forecasts so accuracy can be compared.**
- [ ] **Step 4: Surface baseline vs seasonal forecast and accuracy; do not hide fallback. Commit:** `feat: add seasonality aware forecasts`.

### Task 15: 90-day purchasing/cash forecast

- [ ] **Step 1: Test suggested order date/quantity using stock, incoming, lead time, MOQ, case size and cost fixtures.**
- [ ] **Step 2: Implement weekly 90-day purchasing plan grouped by supplier/currency.**
- [ ] **Step 3: Calculate cash requirement and warnings; never merge unlike currencies into a fake total without conversion data.**
- [ ] **Step 4: Link suggestions to draft-PO creation requiring user confirmation. Commit:** `feat: add purchasing cash forecast`.

### Task 16: What-if simulator

- [ ] **Step 1: Test that scenario overrides do not persist to product/store settings.**
- [ ] **Step 2: Implement demand %, lead-time days, safety-stock days, reorder delay and budget-cap overrides.**
- [ ] **Step 3: Reuse the same forecast/planning services with an immutable scenario input object.**
- [ ] **Step 4: Compare baseline/scenario stockouts, risk, cash need and inventory. Commit:** `feat: add inventory scenario simulator`.

### Task 17: Weekly Executive Report

- [ ] **Step 1: Test deterministic report payload against fixed weekly facts.**
- [ ] **Step 2: Persist historical report snapshots so old reports do not recalculate into different numbers.**
- [ ] **Step 3: Render WordPress admin report with all required sections.**
- [ ] **Step 4: Add configured email schedule/recipients with safe HTML/plain fallback and no customer PII.**
- [ ] **Step 5: Commit:** `feat: add weekly executive report`.

### Task 18: Optional AI explanation layer

- [ ] **Step 1: Define a provider interface that accepts structured evidence and returns commentary; core services cannot depend on a concrete AI provider.**
- [ ] **Step 2: Test disabled/no-key/provider-error paths; deterministic UI/report must remain complete.**
- [ ] **Step 3: Add secure settings for provider configuration without storing secrets in repository code.**
- [ ] **Step 4: Add anomaly/risk and weekly-report summarization prompts that explicitly prohibit inventing quantities/costs/orders.**
- [ ] **Step 5: Clearly label AI narrative and commit:** `feat: add optional AI explanations`.

### Task 19: Navigation consolidation and final regression

- [ ] **Step 1: Map proven screens into `Dashboard / Inventory / Purchasing / Products / Analytics / Reports / Settings`.**
- [ ] **Step 2: Remove/redirect legacy screens only where replacement parity is demonstrated.**
- [ ] **Step 3: Run full regression: activation/upgrade, stock editing, variations, thresholds, units/box, imports/exports, licensing, HPOS, suppliers, POs, receiving, locations, scanner, analytics, alerts, scenarios and reports.**
- [ ] **Step 4: Run migration from a copy/fixture representing plugin 3.3.0 and reconcile pre/post stock totals.**
- [ ] **Step 5: Document known limits and release/rollback procedure. Commit:** `chore: prepare Woo Hoo Manager release`.

## Self-review checklist

Before treating this plan as complete during execution:

- Every selected feature in `PRODUCT-SPEC.md` maps to at least one task above.
- Supplier/PO/location foundations precede metrics that depend on incoming/cost data.
- Historical aggregation precedes forecasts and GMROI.
- Deterministic intelligence precedes alerts/action center/AI.
- No phase requires an external SaaS backend.
- No forecast or AI path directly mutates stock.
- Multi-location changes include reconciliation and recursion protection.
- PO receiving includes idempotency protection.
- Large-store analytics use bounded precomputation instead of removing old caps and scanning live.

## Execution handoff

Execute task-by-task with review checkpoints. The recommended mode is fresh-agent/subagent execution per task where available; otherwise use a separate implementation session with this plan and `docs/CODEX-PROMPT.md` loaded. Do not collapse the plan into a single unreviewed implementation pass.