# Codex Master Prompt — Woo Hoo Manager

Use this prompt when handing the repository to Codex. The repository documentation is the source of truth.

---

You are working on **Woo Hoo Manager**, an evolution of an existing WordPress/WooCommerce plugin currently named **Stock Manager for WooCommerce**.

## Mandatory first steps

Before changing code:

1. Read `README.md`.
2. Read `docs/PRODUCT-SPEC.md` completely.
3. Read `docs/DATA-MODEL.md` completely.
4. Read `docs/ROADMAP.md` completely.
5. Read `docs/superpowers/plans/2026-09-08-woo-hoo-manager.md` completely.
6. Inspect the entire existing plugin structure and identify the current bootstrap, admin screens, AJAX handlers, analytics, low-stock, import/export, settings and licensing behavior.
7. Check git status and current branch. Do not destroy uncommitted user work.
8. Report the current baseline and the exact roadmap phase/task you intend to implement before editing.

## Architecture is already decided

Do **not** propose or perform a rewrite to SaaS, a separate backend, React SPA, external database or microservices.

For this roadmap, keep the application a WordPress/WooCommerce PHP plugin and evolve the current architecture incrementally. Custom WordPress database tables are allowed for ledgers, POs, suppliers, locations and analytics/time-series data.

Do not perform a broad refactor merely because you prefer a different architecture. Refactor only when a concrete task requires it, and keep changes narrow.

## Preserve the existing plugin

The starting plugin already has useful production behavior. Treat it as software to evolve, not disposable scaffolding.

Preserve unless the approved spec explicitly replaces it:

- inline WooCommerce stock manager;
- product/variation support;
- low-stock thresholds;
- units per box and box-rounded reorder quantities;
- running-low/reorder workflow;
- CSV purchase-order export;
- daily/weekly reorder email;
- analytics currently present;
- CSV/XLSX import/export;
- licensing/trial behavior;
- HPOS compatibility.

Do not silently break old settings/meta keys or existing installations. Introduce versioned migrations where needed.

## How to execute

Work **phase by phase** according to `docs/ROADMAP.md`. Do not attempt the entire roadmap in one coding pass.

Within a phase:

1. Select the smallest independently testable task from the implementation plan.
2. Write/extend tests or a reproducible verification first when feasible.
3. Run it and establish the expected failing/baseline state.
4. Implement the smallest complete change.
5. Run targeted verification.
6. Run the relevant regression suite/lint/static checks available in the repository.
7. Review your diff for security, WordPress/WooCommerce correctness, HPOS compatibility and performance.
8. Commit with a focused conventional-style message.
9. Update documentation if the implementation materially changes a documented interface/schema/decision.
10. Continue only when the task is green.

If infrastructure needed for testing does not exist, establish a minimal appropriate test harness in Phase 0 rather than skipping verification for the whole project.

## Non-negotiable engineering rules

- Use WooCommerce CRUD/order/stock APIs rather than assuming orders are WordPress posts. HPOS must remain supported.
- Never update `_stock` directly when WooCommerce stock APIs exist.
- Never mutate stock from a forecast, AI response or dashboard calculation.
- Stock-changing operations require explicit user intent and an audit movement.
- Protect admin actions with capabilities and nonces; sanitize inputs and escape output according to WordPress standards.
- Use `$wpdb->prepare()` for dynamic SQL and `$wpdb->prefix` for plugin tables.
- Database migrations must be versioned and safe to retry.
- Background/backfill jobs must be bounded and retryable; never load an entire large order history into memory in one request.
- Store money as decimal-safe values with currency context; do not use binary floats as persisted money.
- Do not run expensive analytics during storefront requests.
- Cache/precompute dashboards and reports where appropriate.
- Show insufficient-data/low-confidence states instead of invented precision.
- AI is optional. Core calculations must be deterministic and work without any AI provider.
- Never commit API keys, tokens, credentials or store/customer personal data.
- Keep user-facing strings translatable.
- Preserve accessibility and mobile usability for operational screens, especially barcode workflows.

## Forecasting rules

The transparent baseline model in `docs/PRODUCT-SPEC.md` is canonical until a later approved change. Implement its calculations in focused services/classes rather than duplicating formulas across admin screens.

Seasonality may improve the forecast later, but the baseline velocity and its inputs must remain inspectable.

Every forecast-dependent result should carry a data-quality/confidence state.

## Multi-location rules

WooCommerce remains the aggregate sellable-stock source consumed by the storefront. Woo Hoo Manager may maintain per-location balances internally.

For products managed by locations:

`Woo stock = sum(active sellable location balances)`

Use recursion guards around synchronization hooks. Transfers between sellable locations must preserve aggregate quantity.

Never introduce multi-location behavior without migration/reconciliation tests.

## Purchase-order rules

PO status progression and receiving semantics are defined in `docs/PRODUCT-SPEC.md`. Partial receipt is a first-class event.

Receiving must be idempotency-safe: retrying a request/job must not double-increment stock.

Do not delete historical received/cancelled POs merely to simplify state management.

## Analytics and recommendation rules

Recommendations must be explainable. Persist/return the inputs needed by the UI to answer “why?”.

Examples:

- Revenue at Risk must expose shortage units, forecast horizon, price assumption and incoming stock.
- Bundle suggestions must expose co-purchase evidence and margin constraints.
- Health Score must expose component scores.
- GMROI must indicate when history is provisional.
- Pareto must calculate actual concentration rather than assume 80/20.

## AI rules

Do not implement AI before deterministic evidence payloads exist.

When AI is eventually enabled, pass structured facts to the provider and request explanation/summarization only. Treat generated output as commentary, not a source of inventory truth.

## UI direction

The end-state information architecture is:

`Dashboard / Inventory / Purchasing / Products / Analytics / Reports / Settings`

Do not prematurely delete legacy pages. Consolidate only after replacement parity exists.

The Dashboard should prioritize **What Changed?** and the **Today Action Center**. Avoid turning it into a grid of decorative KPIs.

## When requirements conflict

Priority order:

1. `docs/PRODUCT-SPEC.md`
2. `docs/DATA-MODEL.md`
3. `docs/ROADMAP.md`
4. implementation plan
5. existing implementation details

If a meaningful product/architecture ambiguity remains, stop and document the decision needed instead of inventing a large new requirement.

## Definition of done for any task

A task is not complete merely because code was written. It is complete when:

- behavior is implemented end-to-end for the task;
- migrations are safe where applicable;
- permissions/nonces/input/output security are correct;
- targeted tests/verification pass;
- relevant regressions pass;
- no obvious storefront performance regression was introduced;
- docs are updated if contracts changed;
- git diff is reviewed;
- the change is committed cleanly.

## Start now

Begin with **Phase 0 — Preserve and baseline**. Inspect the imported plugin first. Do not start implementing advanced forecasting or dashboards until the baseline is committed, understood and verifiable.

At the end of each completed phase, provide:

- what was implemented;
- files/schema changed;
- tests/checks run and their results;
- migration/rollback considerations;
- known limitations;
- the recommended next phase/task.
