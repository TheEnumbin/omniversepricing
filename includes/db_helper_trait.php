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
    private function check_existance($prd_id, $lang_id, $price, $id_attr = 0, $country = 0, $currency = 0, $group = 0)
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
            WHERE oc.`lang_id` = ' . (int) $lang_id . ' AND oc.`shop_id` = ' . (int) $shop_id . '
            AND oc.`product_id` = ' . (int) $prd_id . ' AND oc.`price` = ' . $price . $attr_q . $curre_q . $countr_q . $group_q
        );

        return $results;
    }

    private function create_insert_query($product, $lang_id, $id_attribute = false, $attr_price = false, $price_type = 'current')
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

                // Convert to specific currency if set
                if ($specific_price['id_currency']) {
                    $price_amount = Tools::convertPrice($price_amount, $specific_price['id_currency']);
                }
                $existing = $this->check_existance($product['id_product'], $lang_id, $price_amount, $specific_price['id_product_attribute'], $specific_price['id_country'], $specific_price['id_currency'], $specific_price['id_group']);

                if (empty($existing)) {
                    if ($q != '') {
                        $q .= ',';
                    }
                    $q .= "\n" . '(' . $product['id_product'] . ',' . $specific_price['id_product_attribute'] . ',' . $specific_price['id_country'] . ',' . $specific_price['id_currency'] . ',' . $specific_price['id_group'] . ',' . $price_amount . ',1,"' . date('Y-m-d') . '",' . $shop_id . ',' . $lang_id . ',' . $omni_tax_include_q . ')';
                }
            }
        }
        if ($id_attribute === false) {
            $id_attribute = null;
        }
        if ($need_default) {
            $existing = $this->check_existance($product['id_product'], $lang_id, $price_amount, $id_attribute);

            if ($id_attribute === null) {
                $id_attribute = 0;
            }
            if (empty($existing)) {
                if ($q != '') {
                    $q .= ',';
                }
                $q .= "\n" . '(' . $product['id_product'] . ',' . $id_attribute . ',0,0,0,' . $price_amount . ',0,"' . date('Y-m-d') . '",' . $shop_id . ',' . $lang_id . ',' . $omni_tax_include_q . ')';
            }
        }
        if ($q != '') {
            $q .= ',' . "\n";
        }
        echo '<pre>';
        print_r($id_attribute);
        echo '</pre>';
        echo __FILE__ . ' : ' . __LINE__;
        echo '<pre>';
        print_r($q);
        echo '</pre>';
        echo __FILE__ . ' : ' . __LINE__;
        return $q;
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

    public function getProductsByIdRange(
        $id_lang,
        $id_start = null,
        $id_end = null,
        $order_by = 'id_product',
        $order_way = 'ASC',
        $id_category = false,
        $only_active = false,
        Context $context = null
    ) {
        if (!$context) {
            $context = Context::getContext();
        }

        $front = true;
        if (!in_array($context->controller->controller_type, ['front', 'modulefront'])) {
            $front = false;
        }

        if (!Validate::isOrderBy($order_by) || !Validate::isOrderWay($order_way)) {
            return;
        }

        if ($order_by == 'id_product' || $order_by == 'price' || $order_by == 'date_add' || $order_by == 'date_upd') {
            $order_by_prefix = 'p';
        } elseif ($order_by == 'name') {
            $order_by_prefix = 'pl';
        } elseif ($order_by == 'position') {
            $order_by_prefix = 'c';
        }

        if (strpos($order_by, '.') > 0) {
            $order_by = explode('.', $order_by);
            $order_by_prefix = $order_by[0];
            $order_by = $order_by[1];
        }

        $sql = 'SELECT p.*, product_shop.*, pl.*, m.`name` AS manufacturer_name, s.`name` AS supplier_name
                FROM `' . _DB_PREFIX_ . 'product` p
                ' . Shop::addSqlAssociation('product', 'p') . '
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.`id_product` = pl.`id_product` ' . Shop::addSqlRestrictionOnLang('pl') . ')
                LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON (m.`id_manufacturer` = p.`id_manufacturer`)
                LEFT JOIN `' . _DB_PREFIX_ . 'supplier` s ON (s.`id_supplier` = p.`id_supplier`)' .
                ($id_category ? ' LEFT JOIN `' . _DB_PREFIX_ . 'category_product` c ON (c.`id_product` = p.`id_product`)' : '') . '
                WHERE pl.`id_lang` = ' . (int) $id_lang .
                    ($id_category ? ' AND c.`id_category` = ' . (int) $id_category : '') .
                    ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '') .
                    ($only_active ? ' AND product_shop.`active` = 1' : '') .
                    (($id_start !== null && $id_end !== null)
                        ? ' AND p.`id_product` BETWEEN ' . (int) $id_start . ' AND ' . (int) $id_end
                        : '') . '
                ORDER BY ' . (isset($order_by_prefix) ? pSQL($order_by_prefix) . '.' : '') . '`' . pSQL($order_by) . '` ' . pSQL($order_way);
        $rq = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        return $rq;
    }

    public function getNextAvailableProductId($start, $final_end)
    {
        $lesser_q = '';

        if ($final_end != '') {
            $lesser_q = ' AND p.id_product <= ' . (int) $final_end;
        }

        $sql = '
            SELECT MIN(p.id_product) AS next_id
            FROM `' . _DB_PREFIX_ . 'product` p
            ' . Shop::addSqlAssociation('product', 'p') . '
            WHERE 1 AND product_shop.`active` = 1' . '
            AND p.id_product > ' . (int) $start . $lesser_q;
        $next_id = Db::getInstance()->getValue($sql);

        return $next_id;
    }
}
