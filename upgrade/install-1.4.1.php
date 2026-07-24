<?php
/**
 * 2007-2022 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2022 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

// Get module instance for hook registration
$module = Module::getInstanceByName('omniversepricing');

if (!$module) {
    return false;
}

// This script adds the sync_flags table for smart cron functionality
$sql = [];

// Check if table exists
$table_check = Db::getInstance()->executeS(
    'SHOW TABLES LIKE \'' . _DB_PREFIX_ . 'omniversepricing_sync_flags\''
);

if (empty($table_check)) {
    // Create the sync_flags table
    $sql[] = 'CREATE TABLE `' . _DB_PREFIX_ . 'omniversepricing_sync_flags` (
        `id_sync_flag` int(11) NOT NULL AUTO_INCREMENT,
        `product_id` int(11) NOT NULL,
        `shop_id` int(11) NOT NULL,
        `status` ENUM(\'pending\', \'processing\', \'synced\') DEFAULT \'pending\',
        `date_added` datetime DEFAULT CURRENT_TIMESTAMP,
        `date_synced` datetime DEFAULT NULL,
        `error_message` TEXT NULL,
        PRIMARY KEY (`id_sync_flag`),
        INDEX `status` (`status`),
        INDEX `product_shop` (`product_id`, `shop_id`),
        UNIQUE KEY `unique_product_shop` (`product_id`, `shop_id`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';
}

// Execute all queries
foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}

// Register hooks for smart cron functionality
$module->registerHook('actionProductAdd');
$module->registerHook('actionProductDelete');
// Note: actionProductUpdate should already be registered, but register it to be safe
$module->registerHook('actionProductUpdate');

return true;
