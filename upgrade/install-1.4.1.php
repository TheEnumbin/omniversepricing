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
 * to license@prestashop.com so we can send you a copy immediately.
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

// This script adds the smart sync columns to support the new sync strategy
$sql = [];

// Add sync_status column if it doesn't exist
$column_check = Db::getInstance()->executeS(
    'SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'omniversepricing_products` LIKE "sync_status"'
);

if (empty($column_check)) {
    $sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'omniversepricing_products`
    ADD COLUMN `sync_status` ENUM(\'pending\', \'synced\') DEFAULT \'synced\' AFTER `with_tax`';
}

// Add last_sync_date column if it doesn't exist
$column_check = Db::getInstance()->executeS(
    'SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'omniversepricing_products` LIKE "last_sync_date"'
);

if (empty($column_check)) {
    $sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'omniversepricing_products`
    ADD COLUMN `last_sync_date` DATE NULL DEFAULT NULL AFTER `sync_status`';
}

// Add index on sync_status for performance
$index_check = Db::getInstance()->executeS(
    'SHOW INDEX FROM `' . _DB_PREFIX_ . 'omniversepricing_products` WHERE Key_name = "sync_status"'
);

if (empty($index_check)) {
    $sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'omniversepricing_products`
    ADD INDEX `sync_status` (`sync_status`)';
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
