{*
* 2007-2024 PrestaShop
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
*  @copyright 2007-2025 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
{if $omniversepricing_text_style == 'before_after'}
    <span class="omniversepricing-notice">{$omniversepricing_text} {$omniversepricing_price}</span>
{elseif $omniversepricing_text_style == 'after_before' }
    <span class="omniversepricing-notice">{$omniversepricing_price} {$omniversepricing_text}</span>
{else}
    <span class="omniversepricing-notice">{$omniversepricing_text}</span>
{/if}
<button data-attr_id="{$omni_prd_attr_id}" data-prd_id="{$omni_prd_id}" id="openPriceChart">View Price History</button>