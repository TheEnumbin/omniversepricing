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

## Module Overview

**OmniversePricing** is a PrestaShop module that implements pricing compliance with the EU Omnibus Directive. It tracks and displays the lowest price of products over the past 30 days, showing price comparison notices on product pages and generating price history charts.

**Version**: 1.2.2 | **Author**: TheEnumbin

## Build/Development Commands

- **No build step required** - PHP and JavaScript are used directly
- **CSS is generated dynamically** via `generateCustomCSS()` method in the main class
- **Clear PrestaShop cache** after template changes: Back Office → Advanced Parameters → Performance
- **Enable debug mode** in `/config/defines.inc.php` (set `define('_PS_MODE_DEV_', true);`) for development

## Architecture

### Main Class: `Omniversepricing` (omniversepricing.php)

**Key Methods:**
| Method | Purpose |
|--------|---------|
| `install()` / `uninstall()` | Database setup via `/sql/install.php`, config registration |
| `getConfigForm()` / `postProcess()` | Admin configuration form handling |
| `omniversepricing_init()` | Main entry point for price calculation |
| `omniversepricing_get_price()` | Retrieves minimum price from history |
| `omniversepricing_insert_data()` | Stores price data in database |
| `hookDisplayProductPriceBlock()` | Shows price notices on product pages |
| `hookActionProductUpdate()` | Records price on product changes |
| `hookDisplayHeader()` | Loads frontend assets (CSS/JS) |

### Controllers

| File | Purpose |
|------|---------|
| `controllers/front/frontajax.php` | AJAX endpoint for chart data |
| `controllers/front/sync.php` | Manual/batch price synchronization |
| `controllers/admin/AdminAjaxOmniverseController.php` | Admin tab for ajax operations |

### Frontend

| File | Purpose |
|------|---------|
| `views/templates/front/omni_front.tpl` | Price notice display on product page |
| `views/templates/front/omni_chart.tpl` | Modal with Chart.js price history |
| `views/js/front.js` | Chart.js visualization, listens for product/combination updates |

### Configuration Options (stored in `ps_configuration`)

All prefixed with `OMNIVERSEPRICING_`:
- `TEXT`, `CHART_LABEL`, etc. - Multi-language values (per language: `TEXT_{id_lang}`)
- `HISTORY_FUNC` - Sync method: `'manual'`, `'hook'`, `'cron'`
- `POSITION` - Notice position: `'after_price'`, `'before_price'`
- `NOTICE_STYLE` - Display style: `'mixed'`, `'badge'`, `'text'`
- `PRICE_WITH_TAX` - Include tax in recorded prices
- `SHOW_IF_CURRENT` - Show notice even if current price is lowest

## Important Concepts

### Context-Aware Pricing
Prices are recorded per: **country + currency + customer group + shop + language**. Always retrieve with matching context.

### Product Variations
The module handles product combinations via `id_product_attribute`. When recording/displaying prices, check both `product_id` AND `id_product_attribute`.

### PrestaShop Events
The module integrates with PrestaShop's event system through hooks. Key hooks registered:
- `displayProductPriceBlock` - Notice display
- `actionProductUpdate` - Price recording on update
- `displayHeader` - Asset loading
- `displayAdminProductsExtra` - Admin price history view

