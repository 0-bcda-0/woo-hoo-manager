# Woo Hoo Manager

WooCommerce operations and inventory-intelligence manager.

This repository evolves the existing **Stock Manager for WooCommerce** plugin without replacing its current WordPress/PHP/WooCommerce architecture.

## Product goal

Turn WooCommerce stock and sales data into concrete daily decisions: what needs attention, what will run out, what should be reordered, where money is tied up, and which actions matter most.

## Core principles

- WooCommerce remains the source of truth for products, orders and sellable stock.
- Preserve the current plugin and extend it incrementally; no SaaS/backend rewrite for now.
- Recommendations must show their inputs and be explainable.
- Forecasts never silently change stock or create orders.
- Deterministic analytics come before AI-generated explanations.
- Expensive calculations run asynchronously/cached so storefront performance is not affected.
- Maintain WooCommerce variations and HPOS compatibility.

## Project documentation

Read these before implementing anything:

1. [`docs/PRODUCT-SPEC.md`](docs/PRODUCT-SPEC.md) — canonical product requirements and formulas.
2. [`docs/DATA-MODEL.md`](docs/DATA-MODEL.md) — persistence and analytics model.
3. [`docs/ROADMAP.md`](docs/ROADMAP.md) — dependency-aware delivery phases.
4. [`docs/CODEX-PROMPT.md`](docs/CODEX-PROMPT.md) — master Codex operating instructions.
5. [`docs/superpowers/plans/2026-09-08-woo-hoo-manager.md`](docs/superpowers/plans/2026-09-08-woo-hoo-manager.md) — implementation plan.

The documentation is intentionally committed with the code so future Codex/ChatGPT sessions do not lose product context.