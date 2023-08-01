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
class AdminAjaxOmniverseController extends ModuleAdminController
{
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
        $context = Context::getContext();
        $lang_id = $context->language->id;
        $shop_id = $context->shop->id;
        // $languages = Language::getLanguages(false);
        
        // foreach ($languages as $lang) {
            
        // }
        $products = Product::getProducts($this->context->language->getId(), 0, 10, 'id_product', 'ASC');
        $insert_q = '';
        foreach($products as $product){
           
            $attributes = Product::getProductAttributesIds($product['id_product']);
            if(isset($attributes) && !empty($attributes)){
                // die(__FILE__ . ' : ' . __LINE__);
            }else{
                $specific_prices = SpecificPrice::getByProductId($product['id_product']);
                $price_amount = $product['price'];
                if(isset($specific_prices) && !empty($specific_prices)){

                    foreach($specific_prices as $specific_price){

                        // Reduction
                        if ($specific_price['reduction_type'] == 'amount') {
                            $reduction_amount = $specific_price['reduction'];

                            // if (!$specific_price['id_currency']) {
                            //     $reduction_amount = Tools::convertPrice($reduction_amount, $id_currency);
                            // }

                            $specific_price_reduction = $reduction_amount;

                            // Adjust taxes if required

                            // if (!$use_tax && $specific_price['reduction_tax']) {
                            //     $specific_price_reduction = $product_tax_calculator->removeTaxes($specific_price_reduction);
                            // }
                            // if ($use_tax && !$specific_price['reduction_tax']) {
                            //     $specific_price_reduction = $product_tax_calculator->addTaxes($specific_price_reduction);
                            // }
                        } else {
                            $specific_price_reduction = $price * $specific_price['reduction'];
                        }
                        $price_amount -= $specific_price_reduction;

                        $existing = $this->check_existance($product['id_product'], $price_amount, $specific_price['id_product_attribute'], $specific_price['id_country'], $specific_price['id_currency'], $specific_price['id_group']);

                        if (empty($existing)) {

                            if($insert_q != ""){
                                $insert_q .= ',';
                            }
                            $insert_q .= '(' . $product['id_product'] . ',' . $specific_price['id_product_attribute'] . ',' . $specific_price['id_country'] . ',' . $specific_price['id_currency'] . ',' . $specific_price['id_group'] . ',' . $price_amount . ',1,"' . date('Y-m-d') . '",' . $shop_id . ',' . $lang_id . ')';
                        }
                    }

                }else{
                    $price_amount = Product::getPriceStatic(
                        (int) $product['id_product'],
                        false
                    );
                    $existing = $this->check_existance($product['id_product'], $price_amount);

                    if (empty($existing)) {

                        if($insert_q != ""){
                            $insert_q .= ',';
                        }
                        $insert_q .= '(' . $product['id_product'] . ',0,0,0,0,' . $price_amount . ',0,"' . date('Y-m-d') . '",' . $shop_id . ',' . $lang_id . ')';
                    }
                }
            }
        }

        if($insert_q != "") {
            $insert_q = "INSERT INTO `" . _DB_PREFIX_ . "omniversepricing_products` (`product_id`, `id_product_attribute`, `id_country`, `id_currency`, `id_group`, `price`, `promo`, `date`, `shop_id`, `lang_id`) VALUES $insert_q";
        }
        echo '<pre>';
        print_r($insert_q);
        echo '</pre>';
        echo __FILE__ . ' : ' . __LINE__;
        die(__FILE__ . ' : ' . __LINE__);
    }

    /**
     * Check if price is alredy available for the product
     */
    private function check_existance($prd_id, $price, $id_attr = 0, $country = 0, $currency = 0, $group = 0)
    {
        $context = Context::getContext();
        $lang_id = $context->language->id;
        $shop_id = $context->shop->id;
        $attr_q = '';
        $curre_q = '';
        $countr_q = '';
        $group_q = '';

        $attr_q = ' AND oc.`id_product_attribute` = ' . $id_attr;
        $curre_q = ' AND oc.`id_currency` = ' . $currency;
        $countr_q = ' AND oc.`id_country` = ' . $country;
        $countr_q = ' AND oc.`id_country` = ' . $group;
        $results = Db::getInstance()->executeS(
            'SELECT *
            FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc
            WHERE oc.`lang_id` = ' . (int) $lang_id . ' AND oc.`shop_id` = ' . (int) $shop_id . '
            AND oc.`product_id` = ' . (int) $prd_id . ' AND oc.`price` = ' . $price . $attr_q . $curre_q . $countr_q . $group_q
        );

        return $results;
    }
}
