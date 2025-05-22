<?php

/**
 * 2007-2020 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
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
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

class OmniversepricingFrontajaxModuleFrontController extends ModuleFrontController
{
    private $variables = [];

    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $is_ajax = $_POST['ajax'];
        $id_product = $_POST['id_product'];
        $attr_id = $_POST['attr_id'];
        $lang_id = $this->context->language->id;
        $shop_id = $this->context->shop->id;
        $attr_q = '';
        $curre_q = '';
        $countr_q = '';
        $group_q = '';
        $inner_q = '';
        if ($attr_id) {
            $attr_q = ' AND oc.`id_product_attribute` = ' . (int) $attr_id;
        }
        $date = date('Y-m-d');
        $date_range = date('Y-m-d', strtotime('-365 days'));
        $q_1 = 'SELECT oc.price FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc 
        WHERE oc.`lang_id` = ' . (int) $lang_id . ' AND oc.`shop_id` = ' . (int) $shop_id . '
        AND oc.`product_id` = ' . (int) $id_product . ' AND oc.date > "' . $date_range . '"' . $attr_q . ' AND oc.id_omniversepricing ' . $inner_q;
        $q_2 = 'SELECT oc.price as omniversepricing_price FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc 
        WHERE oc.`lang_id` = ' . (int) $lang_id . ' AND oc.`shop_id` = ' . (int) $shop_id . '
        AND oc.`product_id` = ' . (int) $id_product . ' AND oc.date > "' . $date_range . '" ' . $attr_q . ' AND oc.`id_currency` = 0 AND oc.`id_country` = 0';
        $result = Db::getInstance()->executeS($q_1 . ' UNION ' . $q_2);

        if (isset($result)) {
            return json_encode($result);
        }
    }
}
