# Codex Master Prompt — Woo Hoo Manager

You are extending the existing WordPress/WooCommerce plugin. Do not redesign its architecture.

## Mandatory source-of-truth order
Before changing code, read completely:
1. `docs/SCOPE-LOCK.md` — highest-priority feature whitelist/exclusions.
2. `docs/PRODUCT-SPEC.md`.
3. `docs/DATA-MODEL.md` only for data needed by approved features.
4. `docs/ROADMAP.md`.
5. `docs/superpowers/plans/2026-09-08-woo-hoo-manager.md`.
6. Existing plugin/runtime code and current branch/status.

If any lower document contains a feature excluded by `SCOPE-LOCK.md`, the scope lock wins and the excluded feature must not be implemented.

## Scope rule
Implement only features explicitly whitelisted in `docs/SCOPE-LOCK.md`. Technical infrastructure is allowed only when necessary for a whitelisted feature and must not become an extra user-facing module.

Explicitly do NOT implement ABC/XYZ segmentation, Product 360 as a separate feature, seasonality-aware forecasting as a separate feature, forecast-accuracy dashboard/module, or any other unapproved feature.

Original brainstorming number #9 means Estimated Lost Sales caused by actual Out-of-Stock periods. Do not remap original user-selected numbers to later spec/roadmap/task numbering.

## Architecture
- Keep the current WordPress/PHP/WooCommerce modular plugin.
- No SaaS/backend rewrite, React SPA, external DB, microservices or native mobile app.
- WooCommerce remains canonical for products, orders and aggregate sellable stock.
- Preserve existing plugin behavior until an approved replacement reaches parity.

## Engineering rules
- WooCommerce CRUD/order/stock APIs; HPOS compatible.
- Never write `_stock` directly when Woo stock APIs exist.
- Explicit user intent for stock mutations plus immutable audit movement.
- Capabilities/nonces/sanitization/escaping/prepared SQL for admin writes.
- Versioned retry-safe migrations.
- Bounded/retryable analytics jobs; no heavy storefront analytics.
- Decimal-safe persisted money with currency context.
- Insufficient-data/confidence states instead of fabricated precision.
- Forecasts/recommendations never silently change stock or create orders.
- AI is optional explanation only and cannot be inventory truth.
- No secrets or customer PII in repository/report payloads.

## Development loop
For each approved task:
1. State which whitelist item it implements or which whitelist item requires the infrastructure.
2. Write/extend a failing test or reproducible verification first where feasible.
3. Confirm the expected RED state.
4. Implement the smallest complete change.
5. Run targeted tests and PHP 7.4/8.1/8.3 regression/syntax/build gates.
6. Review security, Woo/HPOS correctness, performance and scope.
7. Commit/PR only focused changes.
8. Do not add adjacent features because they seem useful.

## Key safety semantics
- Multi-location: Woo sellable stock equals aggregate configured sellable locations; transfers preserve aggregate quantity.
- PO receiving: partial receipts are first-class and retrying a receipt cannot double-increment stock.
- Revenue at Risk/Lost Sales/Health/GMROI/Pareto/bundle recommendations must expose evidence/inputs and insufficient-data states.
- Bundle recommendations never auto-create bundle products.
- 90-day planning never auto-places orders.
- What-if scenarios never mutate production settings/stock.
- Weekly report is complete without AI.

## Definition of done
Behavior implemented end-to-end for the approved task; safe migration where applicable; permissions/input/output security correct; tests/syntax/build green; no storefront performance regression; no unapproved feature added; docs updated only within scope; focused reviewed commit.