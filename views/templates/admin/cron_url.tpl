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
<div class="input-group">
    <div class="form-control-plaintext"><a class="d-block" href="#">{$cron_url}</a></div>
</div>
<div class="alert alert-info" style="margin-top: 10px;">
    <strong>{l s='Optional Parameters:' mod='omniversepricing'}</strong>
    <ul style="margin-bottom: 0;">
        <li><code>price_type=current</code> - {l s='Use current sale price (with discounts applied)' mod='omniversepricing'}</li>
        <li><code>price_type=old_price</code> - {l s='Use old price (without discounts applied)' mod='omniversepricing'}</li>
    </ul>
    <p style="margin-bottom: 0;"><strong>{l s='Example:' mod='omniversepricing'}</strong> {$cron_url}?price_type=old_price</p>
</div>