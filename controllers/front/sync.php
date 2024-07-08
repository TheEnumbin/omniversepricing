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

            for ($i = 0; $i <= $productsCount; $i++) {
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
}
