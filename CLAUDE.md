# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Module Overview

**OmniversePricing** is a PrestaShop module that implements pricing compliance with the EU Omnibus Directive. It tracks and displays the lowest price of products over the past 30 days, showing price comparison notices on product pages and generating price history charts.

**Version**: 1.2.2 | **Author**: TheEnumbin

## Build/Development Commands

This is a traditional PrestaShop module with no modern build system (no npm/yarn, no composer). Development is straightforward:

- **No build step required** - PHP and JavaScript are used directly
- **CSS is generated dynamically** via `generateCustomCSS()` method in the main class
- **Clear PrestaShop cache** after template changes: Back Office → Advanced Parameters → Performance
- **Enable debug mode** in `/config/defines.inc.php` (set `define('_PS_MODE_DEV_', true);`) for development

## Architecture

### Main Class: `Omniversepricing` (omniversepricing.php)

The core module class extending PrestaShop's `Module`. Contains all business logic:

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
| `controllers/front/frontajax.php` | AJAX endpoint for chart data (`module-front-ajax`) |
| `controllers/front/sync.php` | Manual/batch price synchronization |
| `controllers/admin/AdminAjaxOmniverseController.php` | Admin tab for ajax operations |

### Database Schema

**Table**: `ps_omniversepricing_products`

| Fields | Description |
|--------|-------------|
| `id_omniversepricing` | Primary key |
| `product_id`, `id_product_attribute` | Product identification (supports combinations) |
| `id_country`, `id_currency`, `id_group` | Context-aware pricing |
| `price`, `promo` | Price information |
| `date`, `shop_id`, `lang_id` | Metadata |

### Frontend Templates

| File | Purpose |
|------|---------|
| `views/templates/front/omni_front.tpl` | Price notice display on product page |
| `views/templates/front/omni_chart.tpl` | Modal with Chart.js price history |

### Frontend JavaScript

**`views/js/front.js`**: Uses Chart.js for price visualization, listens for PrestaShop events:
- `prestashop.on('updatedProduct')` - Product variant change
- `prestashop.on('updatedProductCombination')` - Combination change

Calls `initMyChart()` on load and after changes.

### Configuration Options (stored in `ps_configuration`)

All prefixed with `OMNIVERSEPRICING_`:
- `TEXT`, `CHART_LABEL`, etc. - Multi-language values (per language: `TEXT_{id_lang}`)
- `HISTORY_FUNC` - Sync method: `'manual'`, `'hook'`, `'cron'`
- `POSITION` - Notice position: `'after_price'`, `'before_price'`
- `NOTICE_STYLE` - Display style: `'mixed'`, `'badge'`, `'text'`
- `PRICE_WITH_TAX` - Include tax in recorded prices
- `SHOW_IF_CURRENT` - Show notice even if current price is lowest
- `AUTO_DELETE_OLD` / `DELETE_DATE` - Automatic data cleanup

## Important Contexts

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

### SQL Union Query
The `omniversepricing_get_price()` method uses a UNION query to find the minimum price across the 30-day history, handling both regular and promotional prices.

## Security Notes

- Database queries use Db::getInstance()->executeS() with proper parameter binding
- Dynamic CSS output is sanitized through `generateCustomCSS()`
- Configuration values are properly escaped using `Tools::htmlentitiesUTF8()`
- AJAX controller validates requests via PrestaShop's controller system
