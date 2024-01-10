<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}
class OmniversepricingSyncModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $date_cron = Configuration::get('OMNIVERSEPRICING_CRON_DATE', '');
        $today = date('j-n-Y');

        if ($today != $date_cron) {
            $context = Context::getContext();
            $lang_id = $context->language->id;
            $shop_id = $context->shop->id;
            $languages = Language::getLanguages(false);
            $shops = Shop::getShops(true, null, true);
            $not_found = true;

            $productsCount = Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product`');

            for ($i = 0; $i <= $productsCount; $i++){
                foreach ($languages as $lang) {
                    $products = Product::getProducts($lang['id_lang'], $i, 1, 'id_product', 'ASC');
                    $insert_q = '';
                    
                    if (isset($products) && !empty($products)) {


                        foreach ($products as $product) {
                            $attributes = $this->getProductAttributesInfo($product['id_product']);

                            if (isset($attributes) && !empty($attributes)) {
                                foreach ($attributes as $attribute) {
                                    $insert_q .= $this->create_insert_query($product, $lang['id_lang'], $shop_id, $attribute['id_product_attribute'], $attribute['price']);
                                }
                            } else {
                                $insert_q .= $this->create_insert_query($product, $lang['id_lang'], $shop_id);
                            }
                        }

                        $insert_q = rtrim($insert_q, ',' . "\n");

                        if ($insert_q != '') {
                            $insert_q = 'INSERT INTO `' . _DB_PREFIX_ . "omniversepricing_products` (`product_id`, `id_product_attribute`, `id_country`, `id_currency`, `id_group`, `price`, `promo`, `date`, `shop_id`, `lang_id`) VALUES $insert_q";
                            $insertion = Db::getInstance()->execute($insert_q);
                        }
                    }
                    $products = [];
                    $attributes = [];
                    $insert_q = '';
                }
            }

            Configuration::updateValue('OMNIVERSEPRICING_CRON_DATE', $today);
        }

        exit;
    }

    private function create_insert_query($product, $lang_id, $shop_id, $id_attribute = false, $attr_price = false)
    {
        $specific_prices = SpecificPrice::getByProductId($product['id_product'], $id_attribute);
        $q = '';
        $context = Context::getContext();
        $need_default = true;
        $omni_tax_include = Configuration::get('OMNIVERSEPRICING_PRICE_WITH_TAX', false);

        if ($omni_tax_include) {
            $omni_tax_include = true;
        } else {
            $omni_tax_include = false;
        }
        $price_amount = Product::getPriceStatic(
            (int) $product['id_product'],
            $omni_tax_include,
            $id_attribute
        );

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
                    $q .= "\n" . '(' . $product['id_product'] . ',' . $specific_price['id_product_attribute'] . ',' . $specific_price['id_country'] . ',' . $specific_price['id_currency'] . ',' . $specific_price['id_group'] . ',' . $price_amount . ',1,"' . date('Y-m-d') . '",' . $shop_id . ',' . $lang_id . ')';
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
                $q .= "\n" . '(' . $product['id_product'] . ',' . $id_attribute . ',0,0,0,' . $price_amount . ',0,"' . date('Y-m-d') . '",' . $shop_id . ',' . $lang_id . ')';
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
        $countr_q = ' AND oc.`id_country` = ' . (int) $group;
        $results = Db::getInstance()->executeS(
            'SELECT *
            FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc
            WHERE oc.`lang_id` = ' . (int) $lang_id . ' AND oc.`shop_id` = ' . (int) $shop_id . '
            AND oc.`product_id` = ' . (int) $prd_id . ' AND oc.`price` = ' . $price . $attr_q . $curre_q . $countr_q . $group_q
        );

        return $results;
    }

    private function getProductAttributesInfo($id_product, $shop_only = false)
    {
        return Db::getInstance()->executeS('
        SELECT pa.id_product_attribute, pa.price
        FROM `' . _DB_PREFIX_ . 'product_attribute` pa' .
        ($shop_only ? Shop::addSqlAssociation('product_attribute', 'pa') : '') . '
        WHERE pa.`id_product` = ' . (int) $id_product);
    }
}
