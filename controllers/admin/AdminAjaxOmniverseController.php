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
        $shop_id = Tools::getValue('shopid');
        $prd_id = Tools::getValue('prdid');
        $id_product_attribute = Tools::getValue('id_product_attribute', 0);
        $omniverse_prices = [];
        $seen = [];
        $results = Db::getInstance()->executeS(
            'SELECT *
            FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc
            WHERE oc.`shop_id` = ' . (int) $shop_id . '
            AND oc.`product_id` = ' . (int) $prd_id . '
            AND oc.`id_product_attribute` = ' . (int) $id_product_attribute . '
            ORDER BY date DESC',
            true
        );

        foreach ($results as $result) {
            // Deduplicate legacy per-language rows (same date/price/promo stored once per language)
            $key = $result['date'] . '|' . $result['price'] . '|' . $result['promo'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

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
        $shop_id = Tools::getValue('shopid');
        $id_product_attribute = Tools::getValue('id_product_attribute', 0);
        $promotext = 'Normal Price';
        $promo = 0;

        if ($pricetype) {
            $promo = 1;
            $promotext = 'Promotional Price';
        }
        $result = Db::getInstance()->insert('omniversepricing_products', [
            'product_id' => (int) $prd_id,
            'id_product_attribute' => (int) $id_product_attribute,
            'price' => $price,
            'promo' => $promo,
            'date' => $promodate,
            'shop_id' => (int) $shop_id,
            'lang_id' => 0,
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
        $call_type = Tools::getValue('call_type');
        $synced_ids = Tools::getValue('synced_ids');
        $synced_ids = json_decode($synced_ids, true);
        $end = 5;
        if ($call_type == '2') {
            $next_start = $this->getNextAvailableProductId($start, $final_end);

            if ($next_start == null) {
                $response = [
                    'success' => 1,
                    'start' => 0,
                    'which' => 6,
                ];
                $resp_extra = [];
                if (isset($synced_ids) && !empty($synced_ids)) {
                    $resp_extra = [
                        'synced_ids' => $synced_ids,
                    ];
                }
                $response = array_merge($response, $resp_extra);
                $response = json_encode($response);
                echo $response;
                exit;
            } else {
                $response = [
                    'success' => 1,
                    'start' => $next_start,
                    'which' => 5,
                ];
                $resp_extra = [];
                if (isset($synced_ids) && !empty($synced_ids)) {
                    $resp_extra = [
                        'synced_ids' => $synced_ids,
                    ];
                }
                $response = array_merge($response, $resp_extra);
                $response = json_encode($response);
                echo $response;
                exit;
            }
        }

        if ($final_end != '') {
            if ($final_end < $start) {
                $response = [
                    'success' => 1,
                    'start' => 0,
                    'which' => 4,
                ];
                $response = json_encode($response);
                echo $response;
                exit;
            } else {
                if (($final_end - $start) < 5) {
                    $end = $final_end;
                } else {
                    $end = (int) $start + (int) $end;
                }
            }
        } else {
            $end = (int) $start + (int) $end;
        }
        // Prices are language-independent: process products ONCE using the default
        // language (needed only for the product_lang name join, not for price data)
        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $not_found = true;

        $products = $this->getProductsByIdRange($id_lang, $start, $end, 'id_product', 'ASC');
        $insert_q = '';

        if (!empty($products)) {
            $not_found = false;

            foreach ($products as $product) {
                $synced_ids[] = (int) $product['id_product'];

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

                // STEP 3.5: Get all product attributes and track regular prices for those without specific prices
                $all_product_attributes = $this->getProductAttributesInfo($product['id_product']);

                if (!empty($all_product_attributes)) {
                    foreach ($all_product_attributes as $attr_info) {
                        // If this attribute doesn't have a specific price, track its regular price
                        if (!isset($attributes_with_specific_prices[$attr_info['id_product_attribute']])) {
                            $insert_q .= $this->create_insert_query_for_default_price(
                                $product,
                                $attr_info['id_product_attribute'],
                                false, // Price impact already included by getPriceStatic() when using attribute ID
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
            }
            $insert_q = rtrim($insert_q, ',' . "\n");

            if ($insert_q != '') {
                $insert_q = 'INSERT INTO `' . _DB_PREFIX_ . "omniversepricing_products` (`product_id`, `id_product_attribute`, `id_country`, `id_currency`, `id_group`, `price`, `promo`, `date`, `shop_id`, `lang_id`, `with_tax`) VALUES $insert_q";
                $insertion = Db::getInstance()->execute($insert_q);
            }
        }

        if ($not_found) {
            $response = [
                'success' => 2,
                'start' => $start,
                'which' => 3,
            ];
            $resp_extra = [];

            if (isset($synced_ids) && !empty($synced_ids)) {
                $resp_extra = [
                    'synced_ids' => $synced_ids,
                ];
            }

            $response = array_merge($response, $resp_extra);
            $response = json_encode($response);
            echo $response;
            exit;
        } else {
            $synced_ids = array_values(array_unique($synced_ids));
            // $next_start = $start + $end;
            $next_start = $end;

            if ($final_end != '' && $next_start > $final_end) {
                $next_start = $final_end;
            } elseif ($next_start == $final_end) {
                $response = [
                    'success' => 1,
                    'start' => 0,
                    'which' => 2,
                ];
                $resp_extra = [];
                if (!empty($synced_ids)) {
                    $resp_extra = [
                        'synced_ids' => $synced_ids,
                    ];
                }

                $response = array_merge($response, $resp_extra);
                $response = json_encode($response);
                echo $response;
                exit;
            }

            $response = [
                'success' => 1,
                'start' => $next_start,
                'synced_ids' => $synced_ids,
                'which' => 1,
            ];
            $response = json_encode($response);
            echo $response;
            exit;
        }
    }
}
