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
        $id_product_attribute = Tools::getValue('id_product_attribute', 0);
        $omniverse_prices = [];
        $results = Db::getInstance()->executeS(
            'SELECT *
            FROM `' . _DB_PREFIX_ . 'omniversepricing_products` oc
            WHERE oc.`lang_id` = ' . (int) $lang_id . ' AND oc.`shop_id` = ' . (int) $shop_id . '
            AND oc.`product_id` = ' . (int) $prd_id . '
            AND oc.`id_product_attribute` = ' . (int) $id_product_attribute . '
            ORDER BY date DESC',
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
        }

        $returnarr = [
            'success' => false,
        ];
        echo json_encode($returnarr);

        exit;
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
        }

        $returnarr = [
            'success' => false,
        ];
        echo json_encode($returnarr);

        exit;
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
        }

        $returnarr = [
            'success' => false,
        ];
        echo json_encode($returnarr);

        exit;
    }

    public function ajaxProcessOmniDataSync()
    {
        // -------------------------------------------------------------------
        // Batch price-history synchronizer.
        // Called repeatedly (one AJAX call per batch) by call_sync_ajax() in
        // views/js/admin.js until a completion response (start = 0) is sent.
        // Each call records prices of a small product-ID range into
        // ps_omniversepricing_products, for every shop language.
        // -------------------------------------------------------------------

        // --- 1. Read request parameters (POSTed by admin.js) ---
        $start = Tools::getValue('start'); // Cursor: first product ID of the current batch
        $final_end = Tools::getValue('end'); // Upper product-ID limit ('' = no limit)
        $price_type = Tools::getValue('price_type'); // 'current' = price with reductions | 'old_price' = price without reductions
        $call_type = Tools::getValue('call_type'); // 1 = normal batch sync | 2 = jump to next active product (gap skipping)
        $synced_ids = Tools::getValue('synced_ids'); // JSON array of product IDs already synced (accumulated client-side)
        $synced_ids = json_decode($synced_ids, true);
        $end =  2; // Batch size: at most 5 product IDs per call (keeps each request short, avoids PHP timeout)

        // ================= BRANCH 1: gap-skipping mode (call_type = 2) =================
        // Entered after a "which = 3" response (empty range). Instead of
        // crawling through empty ID ranges 5 IDs at a time, look up the
        // next ACTIVE product directly.
        if ($call_type == '2') {
            // Smallest ACTIVE product ID > $start (and <= $final_end when set); null if none exists
            $next_start = $this->getNextAvailableProductId($start, $final_end);

            // --- Response A: no active product left in the range -> sync finished ---
            if ($next_start == null) {
                $response = [
                    'success' => 1,
                    'start' => 0, // start = 0 tells the JS client to stop calling
                    'which' => 6, // which = 6 means finish sync because no product to sync before the end range given
                ];
                // synced_ids only echoed back when non-empty (client uses it for the progress label)
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

            // --- Response B: next active product found -> resume normal batch sync there ---
            $response = [
                'success' => 1, // success = 1 makes the JS client re-call with call_type = 1 (normal mode)
                'start' => $next_start, // Next batch starts exactly at the product found
                'which' => 5, // which = 5 means next active product found: jump here and continue normally
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

        // ================= BRANCH 2: normal batch sync (call_type = 1) =================

        // --- Response C: cursor already beyond the requested end -> sync completed ---
        if ($final_end != '') {
            if ($final_end < $start) { // Requested range fully covered
                $response = [
                    'success' => 1,
                    'start' => 0, // start = 0 tells the JS client to stop calling
                    'which' => 4, // which = 4 means sync completed
                ];
                $response = json_encode($response);
                echo $response;
                exit;
            }

            // Compute this batch's end cursor; never step past $final_end
            if (($final_end - $start) < 5) {
                $end = $final_end; // Less than one full batch remains -> final partial batch
            } else {
                $end = (int) $start + (int) $end; // Full batch: start + 5
            }
        } else {
            $end = (int) $start + (int) $end; // No end limit -> always a full batch: start + 5
        }

        // --- 2. Fetch products in [start, end] for EVERY shop language and record prices ---
        $context = Context::getContext();
        $lang_id = $context->language->id; // (kept for context; the trait helpers read Context themselves)
        $shop_id = $context->shop->id; // (same: used inside create_insert_query/check_existance via Context)
        $languages = Language::getLanguages(false); // One pass per shop language
        $not_found = true; // Stays true only if NO language returns products in this range

        foreach ($languages as $lang) {
            // Products whose id_product is BETWEEN $start AND $end (inclusive on both ends)
            $products = $this->getProductsByIdRange($lang['id_lang'], $start, $end, 'id_product', 'ASC');
            $insert_q = ''; // Accumulates VALUES tuples for one bulk INSERT

            if (!empty($products)) {
                $not_found = false; // At least one product exists in this range

                foreach ($products as $product) {
                    // Register this product as processed (client accumulates for the progress display)
                    $synced_ids[] = (int) $product['id_product'];
                    // Every product combination must get its own history row
                    $attributes = $this->getProductAttributesInfo($product['id_product']);
                    if (isset($attributes) && !empty($attributes)) {
                        // Product with combinations: one row per attribute
                        foreach ($attributes as $attribute) {
                            $insert_q .= $this->create_insert_query($product, $lang['id_lang'], $attribute['id_product_attribute'], $attribute['price'], $price_type);
                        }
                    } else {
                        // Simple product without combinations
                        $insert_q .= $this->create_insert_query($product, $lang['id_lang'], false, false, $price_type);
                    }
                }
                // Strip the trailing ",\n" left by create_insert_query() after the last tuple
                $insert_q = rtrim($insert_q, ',' . "\n");

                if ($insert_q != '') { // Empty when every row was a duplicate (check_existance() de-duplication)
                    // Single multi-row INSERT for the whole batch (fast bulk write)
                    $insert_q = 'INSERT INTO `' . _DB_PREFIX_ . "omniversepricing_products` (`product_id`, `id_product_attribute`, `id_country`, `id_currency`, `id_group`, `price`, `promo`, `date`, `shop_id`, `lang_id`, `with_tax`) VALUES $insert_q";
                    $insertion = Db::getInstance()->execute($insert_q);
                }
            }
        }

        // --- Response D: range [start, end] contained no products in any language (ID gap) ---
        if ($not_found) {
            $response = [
                'success' => 2, // success = 2 makes the JS client re-call with call_type = 2 (gap-skipping mode)
                'start' => $start, // Same cursor: the server will locate the next active ID itself
                'which' => 3, // which = 3 means no product found on the current range. Find next availabe product between the given range
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

        // --- 3. Compute the next batch cursor ---
        $synced_ids = array_values(array_unique($synced_ids)); // Batch boundaries overlap (BETWEEN is inclusive), IDs can repeat: dedupe
        $next_start = $end; // Next batch starts where this one ended

        if ($final_end != '' && $next_start > $final_end) {
            $next_start = $final_end; // Clamp: final batch must not run past $final_end
        } elseif ($next_start == $final_end) {
            // --- Response E: batch ended exactly at $final_end -> sync completed ---
            $response = [
                'success' => 1,
                'start' => 0, // start = 0 tells the JS client to stop calling
                'which' => 2, // which = 2 means sync completed
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

        // --- Response F (default): batch synced OK -> continue with the next batch ---
        $response = [
            'success' => 1,
            'start' => $next_start, // Next AJAX call resumes from here
            'synced_ids' => $synced_ids, // Accumulated product IDs (client echoes them back on the next call)
            'which' => 1, // which = 1 means continue sync with the next batch
        ];
        $response = json_encode($response);
        echo $response;
        exit;
    }
}
