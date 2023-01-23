{*
* 2007-2023 PrestaShop
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
*  @copyright 2007-2023 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
<div class="omniversepricing-wrapper">
    <div class="omniversepricing-sec omniversepricing-header">
        <input type="hidden" id="prd_id" name="prd_id" value="{$omniverse_prd_id}">
        <select class="omniversepricing-lang-changer" name="omniversepricing_lang_changer" id="omniversepricing_lang_changer">
            {foreach from=$omniverse_langs item=omniverse_lang}
                {if $omniverse_lang.id_lang == $omniverse_curr_lang}
                    <option selected="selected" value="{$omniverse_lang.id_lang}">{$omniverse_lang.name}</option>
                {else}
                    <option value="{$omniverse_lang.id_lang}">{$omniverse_lang.name}</option>
                {/if}
            {/foreach}
        </select>
    </div>
    <div class="omniversepricing-sec omniversepricing-price-history">
        <div><h2>Omniverse Pricing Section</h3></div>
        <table id="omniversepricing_history_table">
            <tr>
                <th>Date</th>
                <th>Price Amount</th>
                <th>Price Type</th>
                <th>Action</th>
            </tr>
            {foreach from=$omniverse_prices item=omniverse_price}
                <tr class="omniversepricing-history-datam" id="omniversepricing_history_{$omniverse_price.id}">
                    <td>{$omniverse_price.date}</td>
                    <td>{$omniverse_price.price}</td>
                    <td>{$omniverse_price.promotext}</td>
                    <td><button  class="omniversepricing_history_delete btn btn-danger" type="button" value="{$omniverse_price.id}">Delete</button></td>
                </tr>
            {/foreach}
        </table>
    </div>
    <div class="omniversepricing-sec omniversepricing-custom-section">
        <h3>Add Your Custom Price</h3>
        <div class="omniversepricing-custom-fields">
            <input class="omniversepricing-custom-field" type="number" id="price_amount" name="price_amount">
            <input class="omniversepricing-custom-field" type="date" id="promodate" name="promodate">
            <select class="omniversepricing-custom-field omniversepricing-promo" name="price_type" id="price_type">
                <option>Select Price Type</option>
                <option value="1">Promotional</option>
                <option value="0">Normal</option>
            </select>
            <button id="omniversepricing_custom_price_add" class="btn btn-primary" type="button">Add</button>
        </div>
    </div>
</div>
