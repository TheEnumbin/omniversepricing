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
require_once dirname(__FILE__) . '/../../includes/db_helper_trait.php';

class OmniversepricingSyncModuleFrontController extends ModuleFrontController
{
    use DatabaseHelper_Trait;

    public const MAX_EXECUTION_TIME = 50; // seconds (safe under typical 60s PHP limit)
    public const PRODUCT_BATCH_SIZE = 500; // Products per batch
    public const MAX_SERVER_LOAD = 5.0; // Skip sync if server load exceeds this
    public const INSERT_CHUNK_BYTES = 262144; // Flush INSERT buffer at ~256 KB (≈4k tuples) per statement

    public function initContent()
    {
        // CPU safeguard: check server load before proceeding
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if ($load[0] > self::MAX_SERVER_LOAD) {
                exit; // Skip this run if server is busy
            }
        }

        $today = date('j-n-Y');
        $history_func = Configuration::get('OMNIVERSEPRICING_HISTORY_FUNC');

        // Get price_type from GET parameter, fallback to config, then default to 'current'
        $price_type = Tools::getValue('price_type', Configuration::get('OMNIVERSEPRICING_SYNC_PRICE_TYPE') ?: 'current');

        // Validate price_type value
        if (!in_array($price_type, ['current', 'old_price'])) {
            $price_type = 'current';
        }

        // Route based on sync method
        if ($history_func == 'smart_cron') {
            // Smart sync: Only process products flagged as pending
            $this->processSmartSync($price_type);
        } else {
            // Keyset-based sync: Process all products sequentially via id_product cursor
            $this->processSync($price_type);
        }

        exit;
    }

    /**
     * Process Smart Sync: Only sync products flagged as pending
     * Optimized for worst case scenario where all products might be pending
     *
     * @param string $price_type
     * @return void
     */
    private function processSmartSync($price_type)
    {
        $startTime = time();
        $context = Context::getContext();
        $shop_id = $context->shop->id;

        // Keep processing until time limit
        while ((time() - $startTime) < self::MAX_EXECUTION_TIME) {
            // Get batch of pending product IDs for current shop
            $pendingProducts = Db::getInstance()->executeS(
                'SELECT DISTINCT product_id
                FROM `' . _DB_PREFIX_ . 'omniversepricing_sync_flags`
                WHERE `status` = "pending"
                AND `shop_id` = ' . (int) $shop_id . '
                LIMIT ' . (int) self::PRODUCT_BATCH_SIZE
            );

            if (empty($pendingProducts)) {
                // No more pending products - sync complete
                exit;
            }

            $productIds = array_column($pendingProducts, 'product_id');

            // Fetch products by ID. Only id_product is consumed by the
            // create_insert_query_for_*() helpers, so there is no product_lang
            // join: pending products without a translation in the default
            // language are synced too (the old join silently skipped them
            // while their flags were still marked synced).
            $sql = 'SELECT p.`id_product`
                    FROM `' . _DB_PREFIX_ . 'product` p
                    ' . Shop::addSqlAssociation('product', 'p') . '
                    WHERE p.`id_product` IN (' . implode(',', array_map('intval', $productIds)) . ')';

            $products = Db::getInstance()->executeS($sql);

            $processed_ids = [];
            if (!empty($products)) {
                // Batch fetch all attributes for these products
                $allAttributes = $this->getBatchProductAttributes($productIds);

                $insert_q = '';
                foreach ($products as $product) {
                    // In-loop time guard: stop mid-batch gracefully when the
                    // run's time budget is exhausted. Unprocessed products keep
                    // their pending flag and replay on the next cron hit.
                    if ((time() - $startTime) >= self::MAX_EXECUTION_TIME) {
                        break;
                    }

                    // Record every price of this product (specific prices per
                    // attribute/country/currency/group + regular prices) as history rows
                    $insert_q .= $this->create_insert_queries_for_product(
                        $product,
                        $allAttributes[$product['id_product']] ?? [],
                        $price_type
                    );
                    $processed_ids[] = (int) $product['id_product'];

                    // Flush in constant-size chunks so combo-heavy products
                    // cannot grow a single INSERT beyond max_allowed_packet
                    if (strlen($insert_q) >= self::INSERT_CHUNK_BYTES) {
                        $this->flushInsertBuffer($insert_q);
                    }
                }

                // Flush the remainder of the batch
                $this->flushInsertBuffer($insert_q);

                // Mark only the products actually processed as synced; the rest
                // stay pending and replay on the next cron hit
                $ids_to_mark = $processed_ids;
            } else {
                // No product rows matched (e.g. deleted products with lingering
                // flags): mark the whole batch synced so this loop cannot spin
                // on the same pending IDs
                $ids_to_mark = $productIds;
            }

            if (!empty($ids_to_mark)) {
                // Mark processed products as synced
                Db::getInstance()->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'omniversepricing_sync_flags`
                    SET `status` = "synced",
                        `date_synced` = NOW()
                    WHERE `product_id` IN (' . implode(',', array_map('intval', $ids_to_mark)) . ')
                    AND `shop_id` = ' . (int) $shop_id
                );
            }

            // Small sleep to reduce CPU spike
            usleep(10000); // 0.01 seconds
        }
    }

    /**
     * Process sync: Keyset-based processing (id_product cursor)
     *
     * @param string $price_type
     * @return void
     */
    private function processSync($price_type)
    {
        $date_cron = Configuration::get('OMNIVERSEPRICING_CRON_DATE');
        $today = date('j-n-Y');

        // Reset cursor if new day
        if ($today != $date_cron) {
            Configuration::updateValue('OMNIVERSEPRICING_SYNC_LAST_ID', 0);
        }

        $startTime = time();
        $last_id = (int) Configuration::get('OMNIVERSEPRICING_SYNC_LAST_ID', 0);

        // Keep processing until time limit
        while ((time() - $startTime) < self::MAX_EXECUTION_TIME) {
            // Keyset fetch: next PRODUCT_BATCH_SIZE product IDs after the cursor.
            // ID gaps are skipped by the query itself; only id_product is selected
            // (all the create_insert_query_for_*() helpers consume). No
            // product_lang join: prices are language-independent, so products
            // without a translation in the default language are synced too.
            $sql = 'SELECT p.`id_product`
                FROM `' . _DB_PREFIX_ . 'product` p
                ' . Shop::addSqlAssociation('product', 'p') . '
                WHERE p.`id_product` > ' . (int) $last_id . '
                ORDER BY p.`id_product` ASC
                LIMIT ' . (int) self::PRODUCT_BATCH_SIZE;
            $products = Db::getInstance()->executeS($sql);

            if (empty($products)) {
                // No more products - sync complete for today
                Configuration::updateValue('OMNIVERSEPRICING_SYNC_LAST_ID', 0);
                Configuration::updateValue('OMNIVERSEPRICING_CRON_DATE', $today);
                exit;
            }

            // Batch fetch all attributes for these products (single query)
            $productIds = array_column($products, 'id_product');
            $allAttributes = $this->getBatchProductAttributes($productIds);

            $insert_q = '';
            $last_processed_id = $last_id; // highest fully committed product of this batch
            foreach ($products as $product) {
                // In-loop time guard: stop mid-batch gracefully when the run's
                // time budget is exhausted, instead of being killed by the PHP
                // time limit. The batch remainder replays on the next cron hit
                // (dedup makes the overlap a no-op).
                if ((time() - $startTime) >= self::MAX_EXECUTION_TIME) {
                    break;
                }

                // Record every price of this product (specific prices per
                // attribute/country/currency/group + regular prices) as history rows
                $insert_q .= $this->create_insert_queries_for_product(
                    $product,
                    $allAttributes[$product['id_product']] ?? [],
                    $price_type
                );
                $last_processed_id = (int) $product['id_product'];

                // Flush in constant-size chunks so combo-heavy products
                // cannot grow a single INSERT beyond max_allowed_packet.
                // Persisting the cursor right after a successful flush caps
                // replay after a hard kill at one chunk (dedup heals it).
                if (strlen($insert_q) >= self::INSERT_CHUNK_BYTES) {
                    $this->flushInsertBuffer($insert_q);
                    Configuration::updateValue('OMNIVERSEPRICING_SYNC_LAST_ID', $last_processed_id);
                }
            }

            // Flush the remainder of the batch
            $this->flushInsertBuffer($insert_q);

            // Cursor = highest fully processed product of this batch (rows are
            // ORDER BY id_product ASC): gap-proof, next fetch resumes strictly
            // after it. On a time-guard break this is the last processed
            // product, not the batch ceiling.
            Configuration::updateValue('OMNIVERSEPRICING_SYNC_LAST_ID', $last_processed_id);

            // Small sleep to reduce CPU spike (optional - adjust as needed)
            usleep(10000); // 0.01 seconds
        }
    }

    /**
     * Fetch attributes for multiple products in a single query
     * This eliminates the N+1 query problem
     *
     * @param array $productIds Array of product IDs
     *
     * @return array Attributes grouped by product_id
     */
    private function getBatchProductAttributes(array $productIds)
    {
        if (empty($productIds)) {
            return [];
        }

        $sql = 'SELECT pa.id_product_attribute, pa.id_product, pa.price
                FROM `' . _DB_PREFIX_ . 'product_attribute` pa' .
                Shop::addSqlAssociation('product_attribute', 'pa') . '
                WHERE pa.`id_product` IN (' . implode(',', array_map('intval', $productIds)) . ')';

        $result = Db::getInstance()->executeS($sql);

        // Group by product_id
        $grouped = [];
        foreach ($result as $row) {
            $grouped[$row['id_product']][] = [
                'id_product_attribute' => $row['id_product_attribute'],
                'price' => $row['price'],
            ];
        }

        return $grouped;
    }

    /**
     * Execute the buffered INSERT tuples and reset the buffer.
     * Keeps every statement at a constant size (INSERT_CHUNK_BYTES) so a
     * combo-heavy batch cannot exceed max_allowed_packet. Safe to call with
     * an empty buffer.
     *
     * @param string $insert_q Buffered VALUES tuples (passed by reference, reset on flush)
     * @return void
     */
    private function flushInsertBuffer(&$insert_q)
    {
        $chunk = rtrim($insert_q, ',' . "\n");
        if ($chunk != '') {
            Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . "omniversepricing_products` (`product_id`, `id_product_attribute`, `id_country`, `id_currency`, `id_group`, `price`, `promo`, `date`, `shop_id`, `lang_id`, `with_tax`) VALUES $chunk");
        }
        $insert_q = '';
    }
}
