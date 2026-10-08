<?php
/**
 * 2007-2022 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2022 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

trait DatabaseHelper_Trait
{
    private function check_existance($prd_id, $price, $id_attr = 0, $country = 0, $currency = 0, $group = 0)
    {
        $context = Context::getContext();
        $shop_id = $context->shop->id;
        $attr_q = '';
        $curre_q = '';
        $countr_q = '';
        $group_q = '';

        if (!$id_attr) {
            $id_attr = 0;
        }
        $attr_q = ' AND oc.`id_product_attribute` = ' . (int) $id_attr;
        $curre_q = ' AND oc.`id_currency` = ' . (int) $currency;
        $countr_q = ' AND oc.`id_country` = ' . (int) $country;
        $group_q = ' AND oc.`id_group` = ' . (int) $group;
        $results = Db::getInstance()->executeS(
            'SELECT *
            FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc
            WHERE oc.`shop_id` = ' . (int) $shop_id . '
            AND oc.`product_id` = ' . (int) $prd_id . ' AND oc.`price` = ' . $price . $attr_q . $curre_q . $countr_q . $group_q
        );

        return $results;
    }

    private function create_insert_query($product, $id_attribute = false, $attr_price = false, $price_type = 'current')
    {
        // Get specific prices for the attribute if specified
        $specific_prices = SpecificPrice::getByProductId($product['id_product'], $id_attribute);

        // Also get specific prices for the base product (id_attribute = 0)
        // These apply to all combinations unless overridden
        $base_specific_prices = SpecificPrice::getByProductId($product['id_product']);

        // Merge them, prioritizing attribute-specific prices
        if (!empty($base_specific_prices)) {
            // Create a map of existing specific prices by key
            $price_map = [];
            foreach ($specific_prices as $price) {
                $key = $price['id_specific_price'];
                $price_map[$key] = $price;
            }

            // Add base prices that aren't overridden by attribute-specific prices
            foreach ($base_specific_prices as $base_price) {
                $key = $base_price['id_specific_price'];
                // Only add if not already present (attribute-specific takes priority)
                if (!isset($price_map[$key])) {
                    $specific_prices[] = $base_price;
                }
            }
        }
        $omni_tax_include = Configuration::get('OMNIVERSEPRICING_PRICE_WITH_TAX');
        $omni_tax_include_q = 0;
        $q = '';
        $context = Context::getContext();
        $shop_id = $context->shop->id;
        $need_default = true;
        $use_reduct = true;

        if ($price_type == 'old_price') {
            $use_reduct = false;
        }
        if ($omni_tax_include) {
            $omni_tax_include = true;
            $omni_tax_include_q = 1;
        } else {
            $omni_tax_include = false;
            $omni_tax_include_q = 0;
        }
        $price_amount = Product::getPriceStatic(
            (int) $product['id_product'],
            $omni_tax_include,
            $id_attribute,
            6,
            null,
            false,
            $use_reduct
        );

        if ($price_amount === null || $price_amount == 0) {
            return '';
        }
        if (isset($specific_prices) && !empty($specific_prices)) {
            foreach ($specific_prices as $specific_price) {
                if (!$specific_price['id_currency'] && !$specific_price['id_group'] && !$specific_price['id_country']) {
                    $need_default = false;
                }
                // Calculate price for this specific price's customer group using priceCalculation
                // This method accepts id_group parameter directly (8th parameter)
                $specific_price_output = null;
                $price_amount = Product::priceCalculation(
                    $shop_id,  // id_shop
                    (int) $product['id_product'],
                    $specific_price['id_product_attribute'],
                    $specific_price['id_country'] ?: 0,  // id_country (0 if not set)
                    0,  // id_state
                    '',  // zipcode
                    $specific_price['id_currency'] ?: 0,  // id_currency (0 if not set)
                    $specific_price['id_group'] ?: 0,  // id_group (8th parameter) - use the specific price's group
                    1,  // quantity
                    $omni_tax_include,  // use_tax
                    6,  // decimals
                    false,  // only_reduc
                    $use_reduct,  // use_reduc
                    true,  // with_ecotax
                    $specific_price_output,  // specific_price (by reference)
                    true  // use_group_reduction
                );
                // Add attribute price if needed
                if ($attr_price !== false) {
                    $price_amount += $attr_price;
                }

                // Convert price BACK to base/default currency before saving
                // Product::priceCalculation() returns price converted to specific currency if id_currency is set
                // We need to save in base currency so current exchange rates are used when displaying
                // Tools::convertPrice($price, $currency, false) converts FROM currency TO default (divides by rate)
                if ($specific_price['id_currency']) {
                    $price_amount = Tools::convertPrice($price_amount, $specific_price['id_currency'], false);
                }
                $existing = $this->check_existance($product['id_product'], $price_amount, $specific_price['id_product_attribute'], $specific_price['id_country'], $specific_price['id_currency'], $specific_price['id_group']);

                if (empty($existing)) {
                    if ($q != '') {
                        $q .= ',';
                    }
                    $q .= "\n" . '(' . $product['id_product'] . ',' . $specific_price['id_product_attribute'] . ',' . $specific_price['id_country'] . ',' . $specific_price['id_currency'] . ',' . $specific_price['id_group'] . ',' . $price_amount . ',1,"' . date('Y-m-d') . '",' . $shop_id . ',0,' . $omni_tax_include_q . ')';
                }
            }
        }
        if ($id_attribute === false) {
            $id_attribute = null;
        }
        if ($need_default) {
            $existing = $this->check_existance($product['id_product'], $price_amount, $id_attribute);

            if ($id_attribute === null) {
                $id_attribute = 0;
            }
            if (empty($existing)) {
                if ($q != '') {
                    $q .= ',';
                }
                $q .= "\n" . '(' . $product['id_product'] . ',' . $id_attribute . ',0,0,0,' . $price_amount . ',0,"' . date('Y-m-d') . '",' . $shop_id . ',0,' . $omni_tax_include_q . ')';
            }
        }
        if ($q != '') {
            $q .= ',' . "\n";
        }
        return $q;
    }

    /**
     * Create insert query for a specific price
     * Processes a single specific price entry and generates the appropriate INSERT query
     */
    private function create_insert_query_for_specific_price($product, $specific_price, $price_type = 'current')
    {
        $omni_tax_include = Configuration::get('OMNIVERSEPRICING_PRICE_WITH_TAX');
        $omni_tax_include_q = 0;
        $context = Context::getContext();
        $shop_id = $context->shop->id;
        $use_reduct = true;

        if ($price_type == 'old_price') {
            $use_reduct = false;
        }
        if ($omni_tax_include) {
            $omni_tax_include = true;
            $omni_tax_include_q = 1;
        } else {
            $omni_tax_include = false;
            $omni_tax_include_q = 0;
        }

        // Calculate price for this specific price's customer group using priceCalculation
        $specific_price_output = null;
        $price_amount = Product::priceCalculation(
            $shop_id,
            (int) $product['id_product'],
            $specific_price['id_product_attribute'],
            $specific_price['id_country'] ?: 0,
            0,
            '',
            $specific_price['id_currency'] ?: 0,
            $specific_price['id_group'] ?: 0,
            1,
            $omni_tax_include,
            6,
            false,
            $use_reduct,
            true,
            $specific_price_output,
            true
        );

        if ($price_amount === null || $price_amount == 0) {
            return '';
        }

        // Convert price BACK to base/default currency before saving
        // Product::priceCalculation() returns price converted to specific currency if id_currency is set
        // We need to save in base currency so current exchange rates are used when displaying
        // Tools::convertPrice($price, $currency, false) converts FROM currency TO default (divides by rate)
        if ($specific_price['id_currency']) {
            $price_amount = Tools::convertPrice($price_amount, $specific_price['id_currency'], false);
        }

        // Check if already exists
        $existing = $this->check_existance(
            $product['id_product'],
            $price_amount,
            $specific_price['id_product_attribute'],
            $specific_price['id_country'],
            $specific_price['id_currency'],
            $specific_price['id_group']
        );

        if (!empty($existing)) {
            return '';
        }

        $q = '(' . $product['id_product'] . ','
            . $specific_price['id_product_attribute'] . ','
            . $specific_price['id_country'] . ','
            . $specific_price['id_currency'] . ','
            . $specific_price['id_group'] . ','
            . $price_amount . ',1,"' . date('Y-m-d') . '",'
            . $shop_id . ',0,'
            . $omni_tax_include_q . '),' . "\n";

        return $q;
    }

    /**
     * Create insert query for default price
     * Creates an insert query for the default price of an attribute (no specific price)
     */
    private function create_insert_query_for_default_price($product, $id_attribute, $attr_price, $price_type = 'current')
    {
        $omni_tax_include = Configuration::get('OMNIVERSEPRICING_PRICE_WITH_TAX');
        $omni_tax_include_q = 0;
        $context = Context::getContext();
        $shop_id = $context->shop->id;
        $use_reduct = true;

        if ($price_type == 'old_price') {
            $use_reduct = false;
        }
        if ($omni_tax_include) {
            $omni_tax_include = true;
            $omni_tax_include_q = 1;
        } else {
            $omni_tax_include = false;
            $omni_tax_include_q = 0;
        }

        if ($id_attribute === false) {
            $id_attribute = 0;
        }

        $price_amount = Product::getPriceStatic(
            (int) $product['id_product'],
            $omni_tax_include,
            $id_attribute,
            6,
            null,
            false,
            $use_reduct
        );

        if ($price_amount === null || $price_amount == 0) {
            return '';
        }

        // Add attribute price if needed
        if ($attr_price !== false) {
            $price_amount += $attr_price;
        }

        // Check if already exists
        $existing = $this->check_existance($product['id_product'], $price_amount, $id_attribute);

        if (!empty($existing)) {
            return '';
        }

        $q = '(' . $product['id_product'] . ','
            . $id_attribute . ','
            . '0,0,0,'
            . $price_amount . ',0,"' . date('Y-m-d') . '",'
            . $shop_id . ',0,'
            . $omni_tax_include_q . '),' . "\n";

        return $q;
    }

    /**
     * Build all INSERT tuples for one product, mirroring the manual sync
     * semantics:
     *  - one row (promo = 1) per specific price, computed for that rule's own
     *    attribute/country/currency/group context
     *  - regular price rows (promo = 0) for attributes without any specific price
     *  - base row (id_product_attribute = 0) unless a catch-all rule
     *    (all-zero context) exists
     *
     * @param array $product At least ['id_product' => ..]
     * @param array $allAttributes Attributes of the product,
     *                             e.g. [['id_product_attribute' => ..], ...] ([] when none)
     * @param string $price_type 'current' (with reductions) | 'old_price' (without)
     *
     * @return string Comma-separated VALUES tuples ('' when nothing to insert)
     */
    private function create_insert_queries_for_product($product, array $allAttributes, $price_type = 'current')
    {
        $insert_q = '';

        // STEP 1: Get ALL specific prices for this product ONCE
        $all_specific_prices = SpecificPrice::getByProductId($product['id_product']);

        // STEP 2: Track which attributes have specific prices
        $attributes_with_specific_prices = [];

        // STEP 3: Process all specific prices and create insert queries
        if (!empty($all_specific_prices)) {
            foreach ($all_specific_prices as $specific_price) {
                $attr_id = $specific_price['id_product_attribute'];
                if (!isset($attributes_with_specific_prices[$attr_id])) {
                    $attributes_with_specific_prices[$attr_id] = true;
                }
                // Create insert query for this specific price
                $insert_q .= $this->create_insert_query_for_specific_price($product, $specific_price, $price_type);
            }
        }

        // STEP 3.5: Regular prices for attributes without specific prices
        if (!empty($allAttributes)) {
            foreach ($allAttributes as $attribute) {
                // If this attribute doesn't have a specific price, track its regular price
                if (!isset($attributes_with_specific_prices[$attribute['id_product_attribute']])) {
                    // Price impact already included by getPriceStatic() when using attribute ID
                    $insert_q .= $this->create_insert_query_for_default_price(
                        $product,
                        $attribute['id_product_attribute'],
                        false,
                        $price_type
                    );
                }
            }
        }

        // STEP 4: Add base default price (id_attribute = 0) only if no catch-all specific price exists
        $has_catch_all_specific_price = false;
        if (!empty($all_specific_prices)) {
            foreach ($all_specific_prices as $sp) {
                // Check if this specific price applies to ALL groups, ALL currencies, ALL countries
                if ($sp['id_currency'] == 0 && $sp['id_group'] == 0 && $sp['id_country'] == 0) {
                    $has_catch_all_specific_price = true;
                    break;
                }
            }
        }

        // Only add default entry if no catch-all specific price exists
        // This ensures general customers (id_group=0) get tracked even when group-specific prices exist
        if (!$has_catch_all_specific_price) {
            $insert_q .= $this->create_insert_query_for_default_price($product, 0, false, $price_type);
        }

        return $insert_q;
    }

    /**
     * Check if price is alredy available for the product
     */
    private function getProductAttributesInfo($id_product, $shop_only = false)
    {
        return Db::getInstance()->executeS('
        SELECT pa.id_product_attribute, pa.price
        FROM `' . _DB_PREFIX_ . 'product_attribute` pa' .
        ($shop_only ? Shop::addSqlAssociation('product_attribute', 'pa') : '') . '
        WHERE pa.`id_product` = ' . (int) $id_product);
    }

    /**
     * Keyset-paginated product fetch.
     * Returns the next $limit products with an id_product strictly greater
     * than $after_id (optionally bounded by $max_id), ordered ascending.
     * Unlike offset/BETWEEN pagination, cost per page is constant and ID
     * gaps are skipped inside the query itself.
     */
    public function getProductsByIdKeyset($id_lang, $after_id, $limit, $max_id = null, ?Context $context = null)
    {
        if (!$context) {
            $context = Context::getContext();
        }

        $front = true;
        if (!in_array($context->controller->controller_type, ['front', 'modulefront'])) {
            $front = false;
        }

        $sql = 'SELECT p.*, product_shop.*, pl.*, m.`name` AS manufacturer_name, s.`name` AS supplier_name
                FROM `' . _DB_PREFIX_ . 'product` p
                ' . Shop::addSqlAssociation('product', 'p') . '
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.`id_product` = pl.`id_product` ' . Shop::addSqlRestrictionOnLang('pl') . ')
                LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON (m.`id_manufacturer` = p.`id_manufacturer`)
                LEFT JOIN `' . _DB_PREFIX_ . 'supplier` s ON (s.`id_supplier` = p.`id_supplier`)
                WHERE pl.`id_lang` = ' . (int) $id_lang .
                    ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '') . '
                AND p.`id_product` > ' . (int) $after_id .
                    ($max_id !== null ? ' AND p.`id_product` <= ' . (int) $max_id : '') . '
                ORDER BY p.`id_product` ASC
                LIMIT ' . (int) $limit;
        $rq = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        return $rq;
    }
}
