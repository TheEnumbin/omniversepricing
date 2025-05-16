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
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}
require_once dirname(__FILE__) . '/../../includes/db_helper_trait.php';

class AdminAjaxOmniverseController extends ModuleAdminController
{
    use DatabaseHelper_Trait;
    public function ajaxProcessOmniverseChangeLang()
    {
        $lang_id = Tools::getValue('langid');
        $shop_id = Tools::getValue('shopid');
        $prd_id = Tools::getValue('prdid');
        $omniverse_prices = [];
        $results = Db::getInstance()->executeS(
            'SELECT *
            FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc
            WHERE oc.`lang_id` = ' . (int) $lang_id . ' AND oc.`shop_id` = ' . (int) $shop_id . ' AND oc.`product_id` = ' . (int) $prd_id . ' ORDER BY date DESC',
            true
        );

        foreach ($results as $result) {
            $omniverse_prices[$result['id_omniversepricing']]['id'] = $result['id_omniversepricing'];
            $omniverse_prices[$result['id_omniversepricing']]['date'] = $result['date'];
            $omniverse_prices[$result['id_omniversepricing']]['price'] = Context::getContext()->getCurrentLocale()->formatPrice($result['price'], Context::getContext()->currency->iso_code);
            $omniverse_prices[$result['id_omniversepricing']]['promotext'] = 'Normal Price';

            if ($result['promo']) {
                $omniverse_prices[$result['id_omniversepricing']]['promotext'] = 'Promotional Price';
            }
        }

        if (!empty($omniverse_prices)) {
            $returnarr = [
                'success' => true,
                'omniverse_prices' => $omniverse_prices,
            ];
            echo json_encode($returnarr);

            exit;
        } else {
            $returnarr = [
                'success' => false,
            ];
            echo json_encode($returnarr);

            exit;
        }
    }

    /**
     * This function allow to delete users
     */
    public function ajaxProcessAddCustomPrice()
    {
        $prd_id = Tools::getValue('prdid');
        $price = Tools::getValue('price');
        $promodate = Tools::getValue('promodate');
        $pricetype = Tools::getValue('pricetype');
        $lang_id = Tools::getValue('langid');
        $shop_id = Tools::getValue('shopid');
        $promotext = 'Normal Price';
        $promo = 0;

        if ($pricetype) {
            $promo = 1;
            $promotext = 'Promotional Price';
        }
        $result = Db::getInstance()->insert('omniversepricing_products', [
            'product_id' => (int) $prd_id,
            'id_product_attribute' => 0,
            'price' => $price,
            'promo' => $promo,
            'date' => $promodate,
            'shop_id' => (int) $shop_id,
            'lang_id' => (int) $lang_id,
        ]);
        $insert_id = Db::getInstance()->Insert_ID();
        $price_formatted = Context::getContext()->getCurrentLocale()->formatPrice($price, Context::getContext()->currency->iso_code);

        if ($result) {
            $returnarr = [
                'success' => true,
                'date' => $promodate,
                'price' => $price_formatted,
                'promo' => $promotext,
                'id_inserted' => $insert_id,
            ];
            echo json_encode($returnarr);

            exit;
        } else {
            $returnarr = [
                'success' => false,
            ];
            echo json_encode($returnarr);

            exit;
        }
    }

    public function ajaxProcessDeleteCustomPrice()
    {
        $pricing_id = Tools::getValue('pricing_id');

        $result = Db::getInstance()->delete(
            'omniversepricing_products',
            '`id_omniversepricing` = ' . (int) $pricing_id
        );

        if ($result) {
            $returnarr = [
                'success' => true,
            ];
            echo json_encode($returnarr);

            exit;
        } else {
            $returnarr = [
                'success' => false,
            ];
            echo json_encode($returnarr);

            exit;
        }
    }

    public function ajaxProcessOmniDataSync()
    {
        $start = Tools::getValue('start');
        $final_end = Tools::getValue('end');
        $price_type = Tools::getValue('price_type');
        $end = 5;
        if ($final_end != '') {
            if ($final_end <= $start) {
                $response = [
                    'success' => 1,
                    'start' => 0,
                ];
                $response = json_encode($response);
                echo $response;
                exit;
            } else {
                $end = (int) $final_end - (int) $start;
            }
        }
        $context = Context::getContext();
        $lang_id = $context->language->id;
        $shop_id = $context->shop->id;
        $languages = Language::getLanguages(false);
        $not_found = true;
        $synced_ids = [];
        foreach ($languages as $lang) {
            $products = Product::getProducts($lang['id_lang'], $start, $end, 'id_product', 'ASC');
            $insert_q = '';

            if (isset($products) && !empty($products)) {
                $not_found = false;

                foreach ($products as $product) {
                    $synced_ids[] = $product['id_product'];
                    $attributes = $this->getProductAttributesInfo($product['id_product']);
                    if (isset($attributes) && !empty($attributes)) {
                        foreach ($attributes as $attribute) {
                            $insert_q .= $this->create_insert_query($product, $lang['id_lang'], $attribute['id_product_attribute'], $attribute['price'], $price_type);
                        }
                    } else {
                        $insert_q .= $this->create_insert_query($product, $lang['id_lang'], false, false, $price_type);
                    }
                }
                $insert_q = rtrim($insert_q, ',' . "\n");

                if ($insert_q != '') {
                    $insert_q = 'INSERT INTO `' . _DB_PREFIX_ . "omniversepricing_products` (`product_id`, `id_product_attribute`, `id_country`, `id_currency`, `id_group`, `price`, `promo`, `date`, `shop_id`, `lang_id`, `with_tax`) VALUES $insert_q";
                    $insertion = Db::getInstance()->execute($insert_q);
                }
            }
        }

        if ($not_found) {
            $response = [
                'success' => 1,
                'start' => 0,
            ];
            $response = json_encode($response);
            echo $response;
            exit;
        } else {
            array_unique($synced_ids);
            $response = [
                'success' => 1,
                'start' => $start + $end,
                'synced_ids' => $synced_ids,
            ];
            $response = json_encode($response);
            echo $response;
            exit;
        }
        $response = [
            'success' => 0,
        ];
        $response = json_encode($response);
        echo $response;

        exit;
    }

    public function ajaxProcessGetPriceHistory()
    {
        $stable_v = Configuration::get('OMNIVERSEPRICING_STABLE_VERSION');
        $lang_id = $this->context->language->id;
        $shop_id = $this->context->shop->id;
        $attr_q = '';
        $curre_q = '';
        $countr_q = '';
        $group_q = '';
        $inner_q = '';
        if ($id_attr) {
            $attr_q = ' AND oc.`id_product_attribute` = ' . (int) $id_attr;
        }

        if ($stable_v && version_compare($stable_v, '1.0.2', '>')) {
            $curr_id = $this->context->currency->id;
            $curre_q = ' oc2.`id_currency` = ' . (int) $curr_id;
            $country_id = $this->context->country->id;
            $countr_q = ' OR oc2.`id_country` = ' . (int) $country_id;
            $customer = $this->context->customer;
            if ($customer instanceof Customer && $customer->isLogged()) {
                $groups = $customer->getGroups();
                $id_group = implode(', ', $groups);
            } elseif ($customer instanceof Customer && $customer->isLogged(true)) {
                $id_group = (int) Configuration::get('PS_GUEST_GROUP');
            } else {
                $id_group = (int) Configuration::get('PS_UNIDENTIFIED_GROUP');
            }

            $group_q = ' OR oc2.`id_group` IN (' . $id_group . ')';

            $inner_q = 'IN (SELECT oc2.id_omniversepricing FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc2 
                       WHERE ' . $curre_q . $countr_q . $group_q . ')';
        }
        $date = date('Y-m-d');
        $date_range = date('Y-m-d', strtotime('-31 days'));
        $q_1 = 'SELECT oc.price FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc 
        WHERE oc.`lang_id` = ' . (int) $lang_id . ' AND oc.`shop_id` = ' . (int) $shop_id . '
        AND oc.`product_id` = ' . (int) $id . ' AND oc.date > "' . $date_range . '" AND oc.price != "' . $price_amount . '"' . $attr_q . ' AND oc.id_omniversepricing ' . $inner_q;
        $q_2 = 'SELECT MIN(price) as ' . $this->name . '_price FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc 
        WHERE oc.`lang_id` = ' . (int) $lang_id . ' AND oc.`shop_id` = ' . (int) $shop_id . '
        AND oc.`product_id` = ' . (int) $id . ' AND oc.date > "' . $date_range . '" AND oc.price != "' . $price_amount . '"' . $attr_q . ' AND oc.`id_currency` = 0 AND oc.`id_country` = 0';
        $result = Db::getInstance()->executeS($q_1 . ' UNION ' . $q_2);
        if (isset($result)) {
            return json_encode($result);
        }

        return false;
    }

}
