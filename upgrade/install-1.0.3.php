<?php
/**
 * 2007-2020 PrestaShop SA and Contributors
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
 * @copyright 2007-2020 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_3($object, $install = false)
{
    Configuration::updateValue('OMNIVERSEPRICING_STABLE_VERSION', '1.0.3');
    $sql = [];
    $sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'omniversepricing_products` ADD `id_country` INT(11) NULL AFTER `id_product_attribute`;';
    $sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'omniversepricing_products` ADD `id_currency` INT(11) NULL AFTER `id_country`;';
    $sql[] = 'ALTER TABLE `' . _DB_PREFIX_ . 'omniversepricing_products` ADD `id_group` INT(11) NULL AFTER `id_currency`;';

    foreach ($sql as $query) {
        Db::getInstance()->execute($query);
    }

    return true;
}
