# Woo Hoo Manager — Scope Lock

**Status:** CANONICAL / MUST NOT BE EXPANDED  
**Date:** 2026-09-08

This file is the highest-priority product-scope document for the project.

## User-approved original feature IDs

Only these original brainstorming items are approved:

`2, 3, 6, 7, 8, 9, 10, 12, 17, 18, 21, 23, 24, 27, 34, 35, 36, 37, 39`

Additionally approved: **evidence-based bundle recommendations** that suggest which bundles to make from real co-purchase data. These recommendations are separate from original #21 Bundles/Kits.

The numeric IDs refer only to the original brainstorming list supplied by the user. Never remap them using later roadmap/task numbering.

## Canonical approved feature names

- **#2 Inventory Forecasting Engine**
- **#3 Days of Stock / Stock Cover**
- **#6 Smart Replenishment**
- **#7 Stockout Prediction**
- **#8 Revenue at Risk**
- **#9 Estimated Lost Sales caused by Out-of-Stock**
- **#10 Inventory Health Score**
- **#12 Expanded Slow / Dead Stock intelligence**
- **#17 Stock Adjustments**
- **#18 Stock Count / Inventura**
- **#21 Bundles / Kits** with component availability
- **Bundle Recommendations** from real co-purchase evidence
- **#23 Smart Alerts**
- **#24 Anomaly Detection**
- **#27 Product 360°**
- **#34 Seasonality-aware Forecasting**
- **#35 Forecast vs Actual / Forecast Accuracy**
- **#36 Cash Flow / Purchasing Forecast**
- **#37 What-if Simulator**
- **#39 Weekly Executive Report**

## Supporting infrastructure allowed when required

The following are not standalone approved product features, but may remain as supporting infrastructure only where they are necessary for the approved features above:

- supplier cost, supplier SKU, MOQ, pack size, lead time and supplier contact data used by #6/#7/#36;
- persistent incoming-purchase data and receiving used to calculate incoming stock for #6/#7/#36;
- immutable stock movement ledger used by #17/#18 and anomaly detection #24;
- one internal default stock location if required by the movement ledger;
- historical WooCommerce sales facts and daily inventory snapshots;
- deterministic demand/velocity calculations, forecast snapshots and data-quality/confidence flags;
- database migrations, indexes, bounded background jobs and caches.

Supporting infrastructure must not be expanded into an unrelated user-facing module unless the user explicitly approves that feature.

## Explicitly out of scope

These are NOT selected and must not be exposed as product features:

- #4 Supplier Management as a broad standalone management module beyond inputs required by approved features;
- #5 Purchase Order system as a broad standalone ERP-style feature beyond incoming/receiving data required by approved features;
- #11 ABC / XYZ;
- #13 Gross Margin & Profit Analytics as its own dashboard;
- #14 GMROI;
- #15 Inventory Turnover as its own feature;
- #16 Stock History as a standalone feature (movement history may exist as support for #17/#18/#24);
- #19 Barcode Scanner;
- #20 Multi-Warehouse;
- #22 Returns + Damaged Stock as a separate inventory-state system;
- #25 What Changed dashboard;
- #26 Today / Action Center;
- #28 Category / Brand Intelligence;
- #29 Pareto analysis;
- #30 Supplier Performance;
- #31 Landed Cost;
- #32 Price Intelligence;
- #33 Promotion Impact;
- #38 AI;
- #40 Integrations;
- React/SaaS/backend/external-DB/microservice/native-mobile rewrites;
- autonomous purchasing or automatic PO placement.

## Scope enforcement

Before every branch/task/PR:

1. Name the original approved feature number/name it implements.
2. If it is infrastructure, name the approved feature(s) that require it.
3. Check that no unrelated user-facing behavior is introduced.
4. Do not merge extra features.

Document priority:

1. `docs/SCOPE-LOCK.md`
2. direct user instruction
3. `docs/PRODUCT-SPEC.md`
4. `docs/DATA-MODEL.md`
5. `docs/ROADMAP.md`
6. implementation plan

Architecture remains the existing WordPress/PHP/WooCommerce modular plugin unless the user explicitly changes that decision.