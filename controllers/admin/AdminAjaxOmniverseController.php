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

    public const PRODUCT_BATCH_SIZE = 3; // Products per AJAX batch (keyset fetch)

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
        // Batch price-history synchronizer (keyset pagination).
        // Called repeatedly (one AJAX call per batch) by call_sync_ajax() in
        // views/js/admin.js until a completion response (start = 0) is sent.
        // Each call records prices of the next PRODUCT_BATCH_SIZE products
        // after the cursor into ps_omniversepricing_products (prices are
        // language-independent, so products are processed once).
        // -------------------------------------------------------------------

        // --- 1. Read request parameters (POSTed by admin.js) ---
        $start = Tools::getValue('start'); // Cursor: highest product ID synced so far (exclusive)
        $final_end = Tools::getValue('end'); // Upper product-ID limit ('' = no limit)
        $price_type = Tools::getValue('price_type'); // 'current' = price with reductions | 'old_price' = price without reductions
        $synced_ids = Tools::getValue('synced_ids'); // JSON array of product IDs already synced (accumulated client-side)
        $synced_ids = json_decode((string) $synced_ids, true);

        // First call (no synced history yet): the user-entered Start ID itself
        // must be included -> fetch after (Start - 1). Later calls resume
        // strictly after the cursor, so no product is processed twice.
        if ($synced_ids === null) {
            $after_id = (int) $start - 1;
        } else {
            $after_id = (int) $start;
        }
        // Prices are language-independent: process products ONCE using the default
        // language (needed only for the product_lang name join, not for price data)
        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        // Optional inclusive upper bound (the 'end' field). Pure ceiling: it
        // never takes part in batch arithmetic - PRODUCT_BATCH_SIZE defines
        // the batch, ID gaps are skipped by the query itself.
        $max_id = ($final_end !== '' && $final_end !== null) ? (int) $final_end : null;

        // --- 2. Fetch the next batch and record prices ---
        $not_found = true; // Stays true if no products exist after the cursor
        $batch_last_id = 0; // Highest product ID of this batch -> next cursor

        // Next PRODUCT_BATCH_SIZE existing products after $after_id, bounded by $max_id when set
        $products = $this->getProductsByIdKeyset($id_lang, $after_id, self::PRODUCT_BATCH_SIZE, $max_id);
        $insert_q = '';

        if (!empty($products)) {
            $not_found = false;
            // Rows come ORDER BY id_product ASC: the last row holds the batch's highest ID
            $batch_last_id = (int) $products[count($products) - 1]['id_product'];

            foreach ($products as $product) {
                $synced_ids[] = (int) $product['id_product'];

                // Record every price of this product (specific prices per
                // attribute/country/currency/group + regular prices) as history rows
                $all_product_attributes = $this->getProductAttributesInfo($product['id_product']);
                $insert_q .= $this->create_insert_queries_for_product($product, $all_product_attributes, $price_type);
            }
            $insert_q = rtrim($insert_q, ',' . "\n");

            if ($insert_q != '') {
                $insert_q = 'INSERT INTO `' . _DB_PREFIX_ . "omniversepricing_products` (`product_id`, `id_product_attribute`, `id_country`, `id_currency`, `id_group`, `price`, `promo`, `date`, `shop_id`, `lang_id`, `with_tax`) VALUES $insert_q";
                $insertion = Db::getInstance()->execute($insert_q);
            }
        }

        // --- 3. Normalize the accumulated ID list (defensive dedupe) ---
        $synced_ids = array_values(array_unique((array) $synced_ids));

        // --- Response D: no products after the cursor -> range exhausted ---
        if ($not_found) {
            $response = [
                'success' => 1,
                'start' => 0, // start = 0 tells the JS client to stop calling
                'which' => 4, // which = 4 means sync completed (range exhausted)
                'synced_ids' => $synced_ids, // Always included: JS reads synced_ids.length on completion
            ];
            echo json_encode($response);
            exit;
        }

        // --- Response E: batch reached $final_end -> sync completed ---
        if ($max_id !== null && $batch_last_id >= $max_id) {
            $response = [
                'success' => 1,
                'start' => 0, // start = 0 tells the JS client to stop calling
                'which' => 2, // which = 2 means sync completed
                'synced_ids' => $synced_ids,
            ];
            echo json_encode($response);
            exit;
        }

        // --- Response F (default): batch synced OK -> continue with the next batch ---
        $response = [
            'success' => 1,
            'start' => $batch_last_id, // Next AJAX call resumes strictly after this ID
            'synced_ids' => $synced_ids, // Accumulated product IDs (client echoes them back on the next call)
            'which' => 1, // which = 1 means continue sync with the next batch
        ];
        echo json_encode($response);
        exit;
    }
}
