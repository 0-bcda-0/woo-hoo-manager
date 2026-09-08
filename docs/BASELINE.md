# Stock Manager for WooCommerce 3.3.0 — Baseline

This document records the current production baseline before Woo Hoo Manager feature development begins.

## Plugin identity

- Plugin name: `Stock Manager for WooCommerce`
- Version: `3.3.0`
- Text domain: `sheet-stock-sync-woo`
- Requires WordPress: `5.8+`
- Requires PHP: `7.4+`
- WooCommerce minimum declared: `5.0+`
- HPOS compatibility is explicitly declared through WooCommerce `FeaturesUtil`.

## Bootstrap

Entry point: `sheet-stock-sync-woo/sheet-stock-sync-woo.php`

The bootstrap intentionally stays small. It:

- defines plugin constants/options/cron names;
- loads settings immediately because activation depends on them;
- checks WooCommerce availability;
- loads runtime classes;
- instantiates licensing, admin, AJAX, import/export and low-stock services;
- loads translations on `init`;
- supports plugin-specific admin-language override;
- declares HPOS compatibility;
- creates baseline settings/trial timestamp on activation;
- removes scheduled low-stock/licence jobs and analytics transient on deactivation.

## Runtime classes

Current classes under `includes/`:

- `class-ssw-admin.php` — WordPress admin navigation/rendering.
- `class-ssw-ajax.php` — inline/admin AJAX stock operations.
- `class-ssw-analytics.php` — current sales/inventory analytics.
- `class-ssw-builtin.php` — WooCommerce product/stock data abstraction and inline quantity/settings updates.
- `class-ssw-chart.php` — analytics chart rendering/data support.
- `class-ssw-import-export.php` — CSV/XLSX stock import/export.
- `class-ssw-license.php` — trial/licence validation and scheduled checks.
- `class-ssw-lowstock.php` — low-stock list, reorder suggestions, purchase-order CSV and email report scheduling.
- `class-ssw-settings.php` — plugin settings/defaults/languages.

## Current functionality that must not regress

- Searchable/filterable WooCommerce stock manager table.
- Inline stock quantity updates.
- Simple products and variations.
- Per-product low-stock threshold.
- Units/pieces per box.
- Running-low inventory view.
- Suggested reorder quantity rounded to box/case quantity.
- CSV purchase-order export.
- Daily/weekly low-stock/reorder email.
- Current-vs-previous-month analytics: revenue, orders, units and AOV.
- Best sellers and daily revenue chart.
- Current retail stock value.
- Products unsold for 60 days.
- CSV/XLSX bulk stock import/export.
- Trial/licence workflow.
- HPOS compatibility.
- Plugin-level translated UI language support.

## Existing storage/contracts to preserve during migrations

Core constants identify these persisted contracts:

- `ssw_settings`
- `ssw_last_log`
- `ssw_report_log`
- `ssw_license_key`
- `ssw_installed_at`
- `ssw_install_id`
- `ssw_license_check`
- `ssw_report_schedule`
- analytics transient `ssw_analytics_report`

Scheduled hook names:

- `ssw_low_stock_report`
- `ssw_license_check_event`

Existing product metadata for threshold/units-per-box must be treated as migration-sensitive even when later supplier/location tables are introduced.

## Known baseline scalability limits

The current plugin was designed for a smaller store and must not be scaled by simply removing caps and running larger synchronous queries.

Known assumptions from the existing implementation review:

- stock manager listing is capped around 500 products;
- analytics inspect at most roughly 2,000 recent paid orders;
- analytics are cached for about one hour;
- historical inventory snapshots do not yet exist;
- purchase orders are currently export/report concepts, not persisted PO entities;
- there is no stock movement ledger or multi-location balance model.

Woo Hoo Manager replaces these limits through bounded background aggregation and indexed tables, not giant live admin queries.

## Phase 0 safety rule

Before new product functionality is merged:

1. all PHP files must pass syntax validation on every supported CI PHP version;
2. this baseline document remains available for regression review;
3. schema changes must go through a versioned, idempotent migration mechanism;
4. existing options/meta/licensing behavior must not be silently renamed or deleted;
5. `main` remains the rollback reference until Phase 0 is reviewed and merged.

## Baseline verification status

The source is present in GitHub and the bootstrap references resolve to the nine runtime classes listed above. Automated syntax CI is introduced in the Phase 0 branch. Full WordPress/WooCommerce integration tests are not present in the original plugin and are part of the next foundation work.