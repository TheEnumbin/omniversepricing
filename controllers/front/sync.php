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
 * to license@prestashop.com so that we can send you a copy immediately.
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

    const MAX_EXECUTION_TIME = 50; // seconds (safe under typical 60s PHP limit)
    const PRODUCT_BATCH_SIZE = 500; // Products per batch
    const MAX_SERVER_LOAD = 5.0; // Skip sync if server load exceeds this

    public function initContent()
    {
        // CPU safeguard: check server load before proceeding
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if ($load[0] > self::MAX_SERVER_LOAD) {
                exit; // Skip this run if server is busy
            }
        }

        $date_cron = Configuration::get('OMNIVERSEPRICING_CRON_DATE');
        $today = date('j-n-Y');

        // Get price_type from GET parameter, fallback to config, then default to 'current'
        $price_type = Tools::getValue('price_type', Configuration::get('OMNIVERSEPRICING_SYNC_PRICE_TYPE') ?: 'current');

        // Validate price_type value
        if (!in_array($price_type, ['current', 'old_price'])) {
            $price_type = 'current';
        }

        // Reset offset if new day
        if ($today != $date_cron) {
            Configuration::updateValue('OMNIVERSEPRICING_SYNC_OFFSET', 0);
        }

        $startTime = time();
        $offset = (int)Configuration::get('OMNIVERSEPRICING_SYNC_OFFSET', 0);
        $context = Context::getContext();
        $languages = Language::getLanguages(false);

        // Keep processing until time limit
        while ((time() - $startTime) < self::MAX_EXECUTION_TIME) {
            $hasMoreData = false;

            foreach ($languages as $lang) {
                $products = Product::getProducts(
                    $lang['id_lang'],
                    $offset,
                    self::PRODUCT_BATCH_SIZE,
                    'id_product',
                    'ASC'
                );

                if (empty($products)) {
                    // No more products - sync complete for today
                    Configuration::updateValue('OMNIVERSEPRICING_SYNC_OFFSET', 0);
                    Configuration::updateValue('OMNIVERSEPRICING_CRON_DATE', $today);
                    exit;
                }

                $hasMoreData = true;

                // Batch fetch all attributes for these products (single query)
                $productIds = array_column($products, 'id_product');
                $allAttributes = $this->getBatchProductAttributes($productIds);

                $insert_q = '';
                foreach ($products as $product) {
                    $attributes = $allAttributes[$product['id_product']] ?? [];

                    if (!empty($attributes)) {
                        foreach ($attributes as $attribute) {
                            $insert_q .= $this->create_insert_query(
                                $product,
                                $lang['id_lang'],
                                $attribute['id_product_attribute'],
                                $attribute['price'],
                                $price_type
                            );
                        }
                    } else {
                        $insert_q .= $this->create_insert_query(
                            $product,
                            $lang['id_lang'],
                            false,
                            false,
                            $price_type
                        );
                    }
                }

                if ($insert_q != '') {
                    $insert_q = rtrim($insert_q, ',' . "\n");
                    $fullQuery = 'INSERT INTO `' . _DB_PREFIX_ . "omniversepricing_products` (`product_id`, `id_product_attribute`, `id_country`, `id_currency`, `id_group`, `price`, `promo`, `date`, `shop_id`, `lang_id`, `with_tax`) VALUES $insert_q";
                    Db::getInstance()->execute($fullQuery);
                }
            }

            $offset += self::PRODUCT_BATCH_SIZE;
            Configuration::updateValue('OMNIVERSEPRICING_SYNC_OFFSET', $offset);

            // Small sleep to reduce CPU spike (optional - adjust as needed)
            usleep(10000); // 0.01 seconds
        }

        exit;
    }

    /**
     * Fetch attributes for multiple products in a single query
     * This eliminates the N+1 query problem
     *
     * @param array $productIds Array of product IDs
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
                'price' => $row['price']
            ];
        }

        return $grouped;
    }
}
