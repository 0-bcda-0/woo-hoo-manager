# Woo Hoo Manager — Scope Lock

**Status:** CANONICAL / MUST NOT BE EXPANDED  
**Date:** 2026-09-08

This file is the highest-priority product-scope document for the project.

## User-approved original feature IDs

Only these original brainstorming items were selected by the user:

`2, 3, 6, 7, 8, 9, 10, 12, 17, 18, 21, 23, 24, 27, 34, 35, 36, 37, 39`

Additionally approved: **evidence-based bundle recommendations** (recommend which bundles to make; do not auto-create bundle products).

Important correction from the user: **original item #9 is Estimated Lost Sales caused by Out-of-Stock. It is NOT ABC/XYZ.**

The numeric IDs above refer only to the assistant's original brainstorming list. Do not renumber them using later specs, roadmap phases or implementation-task numbers.

## Approved feature whitelist

The implementation may expose only the following new product capabilities that were selected/confirmed for this project:

- Supplier management and supplier-product purchasing data.
- Persistent Purchase Orders and receiving, including partial receiving.
- Revenue at Risk / stockout financial risk.
- Estimated Lost Sales caused by actual OOS periods (#9).
- Inventory Health Score.
- Improved dead/slow-stock intelligence and aging.
- GMROI where cost/history supports it.
- Evidence-based bundle recommendations from real co-purchase data.
- Barcode/mobile browser inventory workflows.
- Multi-location / multi-warehouse inventory foundation and transfers.
- Smart Alerts.
- What Changed? dashboard.
- Today Action Center.
- Pareto analysis.
- 90-day purchasing/cash forecast.
- What-if simulator.
- Optional AI explanation layer only after deterministic data exists.
- Weekly Executive Report.
- Final information-architecture/navigation consolidation (#39), while preserving the existing WordPress/WooCommerce architecture for now.

## Technical foundations allowed but NOT standalone features

The following may exist only because an approved feature requires them. They must not become extra user-facing modules unless separately approved:

- historical sales aggregation;
- daily inventory snapshots;
- deterministic velocity/demand calculations;
- stock-cover and projected-stockout calculations;
- forecast snapshots and confidence/data-quality flags;
- database migrations, indexes, background jobs, caching and audit ledger;
- supplier cost, lead-time, MOQ and pack-size inputs needed by approved purchasing/risk features.

## Explicitly OUT OF SCOPE / remove from plans

These were introduced by later assistant interpretation and are not approved features:

- **ABC/XYZ segmentation.**
- **Product 360** as a separate new feature/module.
- **Seasonality-aware forecasting** as a separate roadmap feature.
- **Forecast-accuracy dashboard/module** as a separate feature.
- Any new SaaS/backend architecture, React SPA, external database or microservice rewrite.
- Any native iOS/Android app.
- Autonomous purchasing or automatic PO placement.
- AI-generated stock forecasts or AI as source of inventory truth.
- Any other feature not present in the approved whitelist above.

If an unapproved idea would be useful, document it nowhere in the active roadmap and do not implement it. Ask the user first.

## Scope enforcement rule

Before every new branch/task/PR:

1. Name the approved whitelist item it implements.
2. If it is only infrastructure, name the approved feature that requires it.
3. Search the change for unrelated user-facing behavior.
4. Do not merge unrelated features.

When documentation conflicts, priority is:

1. `docs/SCOPE-LOCK.md`
2. direct user instruction
3. `docs/PRODUCT-SPEC.md`
4. `docs/DATA-MODEL.md`
5. `docs/ROADMAP.md`
6. implementation plan

Architecture remains the existing WordPress/PHP/WooCommerce modular plugin unless the user explicitly changes that decision.