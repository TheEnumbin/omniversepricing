# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Working Guidelines

**IMPORTANT**: When working on this codebase:

1. **Only do what is explicitly asked** - Do not refactor, optimize, or improve code beyond the specific task requested
2. **Suggestions in separate paragraphs** - If you have suggestions, ideas, or recommendations, present them in a separate paragraph after completing the task
3. **Optimization suggestions separate** - If you see opportunities for optimization or better approaches, mention them separately and clearly as suggestions, not as part of the implementation
4. **No proactive changes** - Do not fix unrelated issues, add features, or make improvements unless specifically requested
5. **Report bugs first** - If you find coding issues or bugs while implementing, STOP and report them to the user before making any changes
6. **Remind about milestones** - Before starting work, remind the user to set a milestone/git commit so they can rollback if needed
7. **Never start implementing until explicitly told** - Analysis, explanation, and reporting are fine, but do not write or edit any code until the user explicitly says to implement the change

## Module Overview

**OmniversePricing** (`omniversepricing`) | **Version**: 1.4.1 | **Author**: TheEnumbin

PrestaShop module implementing EU Omnibus Directive pricing compliance: it records product price history (regular + promotional, per shop/country/currency/customer-group/combination), and displays the "lowest price in the last N days" notice plus a price-history chart on the front office. Compatible with PrestaShop 1.7+.

- No build step - PHP/JS used directly
- Notice styling CSS is regenerated on settings save via `generateCustomCSS()` into `views/css/front_generated.css`
- Clear PrestaShop cache after template changes; enable `_PS_MODE_DEV_` in `/config/defines.inc.php` for development

## Database

Created in `sql/install.php`, dropped in `sql/uninstall.php`.

### `ps_omniversepricing_products` - price history

| Column | Notes |
|--------|-------|
| `id_omniversepricing` | PK |
| `product_id`, `id_product_attribute` | Product / combination (0 = base product) |
| `id_country`, `id_currency`, `id_group` | Pricing context of the row (0 = "all") |
| `price` | decimal(20,6). **Always stored tax-exclusive and in the shop's default currency** - tax/currency conversion happens at display time |
| `promo` | 1 = promotional/specific-price row, 0 = regular price row |
| `date` | Row date |
| `shop_id` | Shop context |
| `lang_id` | **Deprecated** - kept for schema compatibility; new rows write `0`. Prices are language-independent: never filter or loop by language |
| `with_tax` | Legacy tax flag of the recording path |

No unique key on business columns - deduplication is done in code via `DatabaseHelper_Trait::check_existance()` before inserting.

### `ps_omniversepricing_sync_flags` - smart-cron queue

| Column | Notes |
|--------|-------|
| `id_sync_flag` | PK |
| `product_id`, `shop_id` | UNIQUE KEY on the pair - makes `flagProductForSync()` an idempotent upsert |
| `status` | ENUM `pending` / `processing` / `synced` |
| `date_added`, `date_synced` | Queue metadata |

Hooks insert/update rows here in `smart_cron` mode; the cron consumes them (see sync flows).

## Sync Flows

All sync paths build identical history rows through `DatabaseHelper_Trait::create_insert_queries_for_product()` (`includes/db_helper_trait.php`), which for each product:

- Fetches **all** specific prices once (`SpecificPrice::getByProductId()` - **not date-filtered by core**)
- Skips rules whose `from`/`to` window does not contain the sync time (`0000-00-00 00:00:00` = no limit) - expired/future rules must never be recorded as active promos
- Creates one `promo = 1` row per valid specific price, recomputed in **that rule's own** attribute/country/currency/group context via `Product::priceCalculation()`, converted back to default currency when the rule is currency-bound
- Creates regular `promo = 0` rows (via `Product::getPriceStatic()`) for attributes without any valid specific price
- Creates the base row (`id_product_attribute = 0`) unless a **valid** catch-all rule (country=currency=group=0) exists
- `$price_type` switch: `'current'` = price with reductions, `'old_price'` = price without

### 1. Manual Sync (admin, `controllers/admin/AdminAjaxOmniverseController.php`)

"Sync Now" on the Action tab. `views/js/admin.js` calls `ajaxProcessOmniDataSync()` repeatedly - one AJAX call per batch, client-driven **keyset pagination**: cursor `start` (last synced product ID), 5 products (`PRODUCT_BATCH_SIZE`) per call, range bounded by the Sync Start/Sync End settings, `synced_ids` accumulated client-side. Responses: `which=1` continue, `which=2` end reached, `which=4` range exhausted (`start=0` stops the JS loop). A full-range run sets `OMNIVERSEPRICING_INITIAL_SYNC_DONE` so smart cron skips its initial sweep. The same controller also handles custom price add/delete and the per-product price list (`ajaxProcessOmniverseChangeLang`).

### 2. Cron Sync (`controllers/front/sync.php::processSync`)

Cron URL: `{shop}/module/omniversepricing/sync` (per shop context, shown on the Action tab). Daily full sweep with a **persisted keyset cursor** (`OMNIVERSEPRICING_SYNC_LAST_ID`), reset each new day via `OMNIVERSEPRICING_CRON_DATE`. 500 products per batch, 50 s time budget (`MAX_EXECUTION_TIME`), INSERT buffer flushed in ~256 KB chunks; the cursor is persisted after each flush so a hard kill replays at most one chunk (dedup heals overlap). Completing a sweep sets `OMNIVERSEPRICING_INITIAL_SYNC_DONE = 1`. Guards: skips the run when server load > 5 (`sys_getloadavg`) and when `OMNIVERSEPRICING_STOP_RECORD` is on.

### 3. Smart Cron (`sync.php::processSmartSync`)

After the initial full sweep (`OMNIVERSEPRICING_INITIAL_SYNC_DONE = 1`), the same cron URL only processes products flagged `pending` in `omniversepricing_sync_flags` (flagged by hooks on product/attribute/specific-price changes), marks them `synced` in the same transaction loop. Same batch/time/load/stop guards as the cron sync.

### 4. Hook Recording - `w_change` (omniversepricing.php)

Product add/update, attribute add/update, specific price add/update hooks call `recordProductHistory()` -> `create_insert_queries_for_product()` and insert immediately (unless `OMNIVERSEPRICING_STOP_RECORD`).

### 5. Hook Recording - `w_hook` (omniversepricing.php)

Records lazily on front-office page view inside `omniversepricing_init()`: dedupe via `omniversepricing_check_existance()`, then `omniversepricing_insert_data()` with the **visitor-context** current price (`getPriceStatic` with reductions; `promo` derived from `has_discount`). Does not enumerate specific prices - date validity is implicit because only the currently active rule can drive the displayed price.

### Shared switches

- `OMNIVERSEPRICING_STOP_RECORD` - master pause, honored by hooks and both cron paths (pending flags stay pending and replay later)
- `OMNIVERSEPRICING_SYNC_PRICE_TYPE` - which price syncs record: `current` (with reductions) or `old_price`
- Auto-delete: with `OMNIVERSEPRICING_AUTO_DELETE_OLD`, `hookDisplayBackOfficeHeader()` once per day deletes history rows older than `OMNIVERSEPRICING_DAYS_LIMIT` (date marker: `OMNIVERSEPRICING_DELETE_DATE`)

## Frontend (notice display)

1. `hookDisplayHeader()` (every page): enqueues `front.css`, the generated `front_generated.css`, `front.js`, `chart.js`, and exposes config to JS via `Media::addJsDef` (front-ajax URL, chart labels, colors).
2. Notice rendering depends on the configured position (hooks are re-registered/unregistered in `postProcess()` on save):
   - `after_price` / `old_price` / `price` -> `hookDisplayProductPriceBlock()`
   - `footer_product` -> `hookDisplayFooterProduct()`
   - `with_custom_hook` -> theme calls `hookDisplayOmniverseNotice()`
3. `omniversepricing_init()` computes the current tax-exclusive price, optionally records history (`w_hook`), fetches the minimum price via `omniversepricing_get_price()` (MIN within the Days Limit window, excluding the current price; context-matched rows UNION default-context fallback rows), and computes the +/- percentage against the current price.
4. `omniversepricing_show_notice()` renders `views/templates/front/omni_front.tpl`. Text styles (`OMNIVERSEPRICING_NOTICE_STYLE`): `before_after` = "text price", `after_before` = "price text", `mixed` = placeholders `{{omni_price}}` / `{{omni_percent}}` replaced inside the configured text. A chart link (`#openPriceChart` with `data-prd_id` / `data-attr_id`) is appended on the product page when the chart is enabled.
5. Visibility: `OMNIVERSEPRICING_NOTICE_PAGE` (all pages vs product page only) and `OMNIVERSEPRICING_SHOW_ON` (all products vs discounted only, based on `has_discount`). `OMNIVERSEPRICING_SHOW_IF_CURRENT` shows the notice even when the current price is the lowest.
6. Back office: `hookDisplayAdminProductsExtra()` renders a per-product price-history table (combination selector, add/delete custom price rows) in `views/templates/admin/price_history.tpl`, driven by `admin.js` + the admin AJAX controller.

## Chart

1. When `OMNIVERSEPRICING_SHOW_CHART` is enabled, `hookDisplayFooter()` echoes `views/templates/front/omni_chart.tpl` on product pages - a modal (`#priceChartModal`) containing `<canvas id="priceHistoryChart">`.
2. `views/js/front.js` runs `initMyChart()` on page load and re-runs it on the PrestaShop events `updatedProduct` / `updatedProductCombination`, so switching combinations re-binds the chart link to the new attribute ID.
3. Clicking `#openPriceChart` POSTs `id_product` + `attr_id` to the front AJAX controller (`controllers/front/frontajax.php`), which returns JSON `[{date: 'Y/m/d', price}]` for the last **365 days** of that product/attribute (context-matched rows UNION default-context rows), with prices converted to the display currency and tax applied via `omniApplyTax()`.
4. `front.js` renders a Chart.js line chart in the modal (labels/colors come from the Chart settings tab), destroying the previous chart instance on re-open; the modal closes on backdrop or close-button click.

## Settings Tabs (module configure form, `getConfigForm()`)

### General

How history is kept (`OMNIVERSEPRICING_HISTORY_FUNC`: `manual` / `w_change` / `w_cron` / `smart_cron` / `w_hook`), where the notice shows (`all_pages` / `single`), which products show it (`all_prds` / `discounted`), price with/without tax, show notice if current price is lowest, notice text style (`before_after` / `after_before` / `mixed`), percent increase/decrease indicator, auto-delete old data + days limit (default 30).

### Content

The notice text (multi-language, supports `{{omni_price}}` and `{{omni_percent}}`) and the notice position (`after_price`, `old_price` (= before old price), `price`, `footer_product`, `with_custom_hook`). Saving re-registers/unregisters the matching display hooks; the custom-hook code snippet is shown for the custom-hook position.

### Design

Notice background color, text color, font size, font weight, padding. Saved values are regenerated into `views/css/front_generated.css` via `generateCustomCSS()`.

### Action

Stop recording (`OMNIVERSEPRICING_STOP_RECORD`), reset price history (truncates both tables, clears the cron cursor/flags and forces a full re-sweep), reset cron values (same reset without truncating history), delete data older than the days limit immediately, which price to keep (`current` sale price vs `old_price` without reductions), Sync Start / Sync End product-ID range, the "Sync Products Now" button (admin AJAX batch sync), and the per-shop cron URL.

### Chart

Enable/disable the chart, chart link text, chart/date/price axis labels (all multi-language), chart link color, line color, and background color.
