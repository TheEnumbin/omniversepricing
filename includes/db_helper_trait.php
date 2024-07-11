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

    private function create_insert_query($product, $lang_id, $id_attribute = false, $attr_price = false)
    {
        $specific_prices = SpecificPrice::getByProductId($product['id_product'], $id_attribute);
        $omni_tax_include = Configuration::get('OMNIVERSEPRICING_PRICE_WITH_TAX', false);
        $omni_tax_include_q = 0;
        $q = '';
        $context = Context::getContext();
        $shop_id = $context->shop->id;
        $need_default = true;

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
            $id_attribute
        );
        if ($price_amount === null || $price_amount == 0) {
            return '';
        }
        if (isset($specific_prices) && !empty($specific_prices)) {
            foreach ($specific_prices as $specific_price) {
                if (!$specific_price['id_currency'] && !$specific_price['id_group'] && !$specific_price['id_country']) {
                    $need_default = false;
                }

                if ($specific_price['id_currency']) {
                    $price_amount = $product['price'];

                    if ($specific_price['reduction_type'] == 'amount') {
                        $reduction_amount = $specific_price['reduction'];
                        $reduction_amount = Tools::convertPrice($reduction_amount, $specific_price['id_currency']);
                        $attr_price = Tools::convertPrice($attr_price, $specific_price['id_currency']);
                        $price_amount = Tools::convertPrice($price_amount, $specific_price['id_currency']);
                        $price_amount += $attr_price;
                        $specific_price_reduction = $reduction_amount;
                        $address = new Address();
                        $use_tax = Configuration::get('OMNIVERSEPRICING_PRICE_WITH_TAX', false);
                        $tax_manager = TaxManagerFactory::getManager($address, Product::getIdTaxRulesGroupByIdProduct((int) $product['id_product'], $context));
                        $product_tax_calculator = $tax_manager->getTaxCalculator();

                        if (!$use_tax && $specific_price['reduction_tax']) {
                            $specific_price_reduction = $product_tax_calculator->removeTaxes($specific_price_reduction);
                        }
                        if ($use_tax && !$specific_price['reduction_tax']) {
                            $specific_price_reduction = $product_tax_calculator->addTaxes($specific_price_reduction);
                        }
                    } else {
                        $attr_price = Tools::convertPrice($attr_price, $specific_price['id_currency']);
                        $price_amount = Tools::convertPrice($price_amount, $specific_price['id_currency']);
                        $price_amount += $attr_price;
                        $specific_price_reduction = $price_amount * $specific_price['reduction'];
                    }
                    $price_amount -= $specific_price_reduction;
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
}
