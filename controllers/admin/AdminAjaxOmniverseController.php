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
        $languages = Language::getLanguages(false);
        $end = 5;
        $not_found = true;
        foreach ($languages as $lang) {
            $products = Product::getProducts($lang['id_lang'], $start, $end, 'id_product', 'ASC');
            $insert_q = '';

            if(isset($products) && !empty($products)){
                $not_found = false;
                foreach($products as $product){

                    $attributes = $this->getProductAttributesInfo($product['id_product']);
        
                    if(isset($attributes) && !empty($attributes)){

                        foreach($attributes as $attribute){
                            $insert_q .= $this->create_insert_query($product, $lang['id_lang'], $attribute['id_product_attribute'], $attribute['price']);
                        }
                    }else{
                        $insert_q .= $this->create_insert_query($product, $lang['id_lang']);
                    }
                }

                $insert_q = rtrim($insert_q, ',' . "\n");
        
                if($insert_q != "") {
                    $insert_q = "INSERT INTO `" . _DB_PREFIX_ . "omniversepricing_products` (`product_id`, `id_product_attribute`, `id_country`, `id_currency`, `id_group`, `price`, `promo`, `date`, `shop_id`, `lang_id`) VALUES $insert_q";
                    $insertion = Db::getInstance()->execute($insert_q);
                }
            }
        }

        if($not_found){
            $response = [
                'success' => 1,
                'start' => 0,
            ];
            $response = json_encode($response);
            echo $response;
            die();
        }else{
            $response = [
                'success' => 1,
                'start' => $start + $end,
            ];
            $response = json_encode($response);
            echo $response;
            die();
        }
        
        
        $response = [
            'success' => 0,
        ];
        $response = json_encode($response);
        echo $response;
        die();
    }

    private function create_insert_query($product, $lang_id, $id_attribute = false, $attr_price = false){
        $specific_prices = SpecificPrice::getByProductId($product['id_product'], $id_attribute);
        $price_amount = $product['price'];
        $q = '';
        $context = Context::getContext();
        $shop_id = $context->shop->id;
        $need_default = true;

        if(isset($specific_prices) && !empty($specific_prices)){

            foreach($specific_prices as $specific_price){
                // Reduction
                if(!$specific_price['id_currency'] && !$specific_price['id_group'] && !$specific_price['id_country']){
                    $need_default = false;
                }
                if ($specific_price['reduction_type'] == 'amount') {
                    $reduction_amount = $specific_price['reduction'];

                    if (!$specific_price['id_currency']) {
                        $reduction_amount = Tools::convertPrice($reduction_amount, $context->currency->id);
                    }else{
                        $reduction_amount = Tools::convertPrice($reduction_amount, $specific_price['id_currency']);
                        $attr_price = Tools::convertPrice($attr_price, $specific_price['id_currency']);
                        $price_amount = Tools::convertPrice($price_amount, $specific_price['id_currency']);
                        $price_amount += $attr_price;
                    }

                    $specific_price_reduction = $reduction_amount;

                    // Adjust taxes if required
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
                $existing = $this->check_existance($product['id_product'], $lang_id, $price_amount, $specific_price['id_product_attribute'], $specific_price['id_country'], $specific_price['id_currency'], $specific_price['id_group']);
                
                if (empty($existing)) {
                    
                    if($q != ""){
                        $q .= ',';
                    }
                    $q .= "\n" . '(' . $product['id_product'] . ',' . $specific_price['id_product_attribute'] . ',' . $specific_price['id_country'] . ',' . $specific_price['id_currency'] . ',' . $specific_price['id_group'] . ',' . $price_amount . ',1,"' . date('Y-m-d') . '",' . $shop_id . ',' . $lang_id . ')';
                }
            }

        }

        if($id_attribute === false){
            $id_attribute = null;
        }

        if($need_default){
            $price_amount = Product::getPriceStatic(
                (int) $product['id_product'],
                false,
                $id_attribute
            );
            $existing = $this->check_existance($product['id_product'], $lang_id, $price_amount, $id_attribute);
    
            if($id_attribute === null){
                $id_attribute = 0;
            }
    
            if (empty($existing)) {
                if($q != ""){
                    $q .= ',';
                }
                $q .= "\n" . '(' . $product['id_product'] . ',' . $id_attribute . ',0,0,0,' . $price_amount . ',0,"' . date('Y-m-d') . '",' . $shop_id . ',' . $lang_id . ')';
            }
        }

        if($q != ''){
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

        if(!$id_attr){
            $id_attr = 0;
        }
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

    private function getProductAttributesInfo($id_product, $shop_only = false)
    {
        return Db::getInstance()->executeS('
        SELECT pa.id_product_attribute, pa.price
        FROM `' . _DB_PREFIX_ . 'product_attribute` pa' .
        ($shop_only ? Shop::addSqlAssociation('product_attribute', 'pa') : '') . '
        WHERE pa.`id_product` = ' . (int) $id_product);
    }
}
