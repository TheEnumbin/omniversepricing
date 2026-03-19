/**
* 2007-2024 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web at this URL, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2026 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*
* Don't forget to prefix your containers with your own identifier
* to avoid any conflicts with others containers.
*/
$(document).ready(function () {
    // Handle combination selector change
    $(document).on('change', '#omniversepricing_combination_selector', function () {
        var $combVal = $(this).val();
        var $langVal = $('#omniversepricing_lang_changer').val();
        var $prdid = $('#prd_id').val();
        $.ajax({
            type: 'POST',
            url: omniversepricing_ajax_url,
            dataType: 'html',
            data: {
                controller: 'AdminAjaxOmniverse',
                action: 'OmniverseChangeLang',
                prdid: $prdid,
                langid: $langVal,
                id_product_attribute: $combVal,
                shopid: omniversepricing_shop_id,
                ajax: true
            },
            success: function (data) {
                var $data = JSON.parse(data);
                
                if (typeof $data.success !== 'undefined' && $data.success) {
                    $('#omniversepricing_history_table').find(".omniversepricing-history-datam").remove();
                    $.each($data.omniverse_prices, function (key, value) {
                        $('#omniversepricing_history_table').append('<tr class="omniversepricing-history-datam" id="omniversepricing_history_' + value.id + '">'
                            + '<td>' + value.date + '</td><td>' + value.price + '</td><td>' + value.promotext + '</td>'
                            + '<td><button  class="omniversepricing_history_delete btn btn-danger" type="button" value="' + value.id + '">Delete</button></td>'
                            + '</tr>');
                    });
                }
            }
        });
    });

    // Handle language selector change
    $(document).on('change', '#omniversepricing_lang_changer', function () {
        var $val = $(this).val();
        var $prdid = $('#prd_id').val();
        var $combVal = $('#omniversepricing_combination_selector').val();
        $.ajax({
            type: 'POST',
            url: omniversepricing_ajax_url,
            dataType: 'html',
            data: {
                controller: 'AdminAjaxOmniverse',
                action: 'OmniverseChangeLang',
                prdid: $prdid,
                langid: $val,
                id_product_attribute: $combVal,
                shopid: omniversepricing_shop_id,
                ajax: true
            },
            success: function (data) {
                var $data = JSON.parse(data);
                if (typeof $data.success !== 'undefined' && $data.success) {
                    $('#omniversepricing_history_table').find(".omniversepricing-history-datam").remove();
                    $.each($data.omniverse_prices, function (key, value) {
                        $('#omniversepricing_history_table').append('<tr class="omniversepricing-history-datam" id="omniversepricing_history_' + value.id + '">'
                            + '<td>' + value.date + '</td><td>' + value.price + '</td><td>' + value.promotext + '</td>'
                            + '<td><button  class="omniversepricing_history_delete btn btn-danger" type="button" value="' + value.id + '">Delete</button></td>'
                            + '</tr>');
                    });
                }
            }
        });
    });
    $(document).on('click', '#omniversepricing_custom_price_add', function () {
        var $prdid = $('#prd_id').val();
        var $price = $('#price_amount').val();
        var $price_type = $('#price_type').val();
        var $promodate = $('#promodate').val();
        var $langid = $('#omniversepricing_lang_changer').find(":selected").val();
        var $id_product_attribute = $('#omniversepricing_combination_selector').val();
        $.ajax({
            type: 'POST',
            url: omniversepricing_ajax_url,
            dataType: 'html',
            data: {
                controller: 'AdminAjaxOmniverse',
                action: 'AddCustomPrice',
                prdid: $prdid,
                price: $price,
                pricetype: $price_type,
                promodate: $promodate,
                langid: $langid,
                shopid: omniversepricing_shop_id,
                id_product_attribute: $id_product_attribute,
                ajax: true
            },
            success: function (data) {
                var $data = JSON.parse(data);
                if (typeof $data.success !== 'undefined' && $data.success) {
                    $('#omniversepricing_history_table').append('<tr class="omniversepricing-history-datam"  id="omniversepricing_history_' + $data.id_inserted + '">'
                        + '<td>' + $data.date + '</td><td>' + $data.price + '</td><td>' + $data.promo + '</td>'
                        + '</tr>');
                    $('#price_amount').val("");
                    $('#promodate').val("");
                    $('#price_type').prop('selectedIndex', 0);
                }
            }
        });
    });
    $(document).on('click', '.omniversepricing_history_delete', function () {
        var $val = $(this).val();
        $.ajax({
            type: 'POST',
            url: omniversepricing_ajax_url,
            dataType: 'html',
            data: {
                controller: 'AdminAjaxOmniverse',
                action: 'DeleteCustomPrice',
                pricing_id: $val,
                ajax: true
            },
            success: function (data) {
                var $data = JSON.parse(data);
                if (typeof $data.success !== 'undefined' && $data.success) {
                    $('#omniversepricing_history_' + $val).remove();
                }
            }
        });
    });
    var stop_sync = 1;
    $(document).on('click', '#omni_sync_bt', function () {
        let $start = $("#omni_sync_start").val();
        let $end = $("#omni_sync_end").val();
        let $omni_price_type = $("#omni_price_type").val();
        stop_sync = 0
        $(".omni-sync-loader").show();
        $("#omni_sync_stop").removeClass("hidden");
        if ($start == '') {
            if ($end == '') {
                call_sync_ajax(0, '', $omni_price_type, 1);
            } else {
                call_sync_ajax(0, $end, $omni_price_type, 1);
            }
        } else {
            // $start = $start;
            if ($end == '') {
                call_sync_ajax($start, '', $omni_price_type, 1);
            } else {
                call_sync_ajax($start, $end, $omni_price_type, 1);
            }
        }
    });

    $(document).on('click', '#omni_sync_stop', function () {
        stop_sync = 1
    });

    function call_sync_ajax(start, $end, price_type, call_type = 1, synced_ids = '') {
        let completed_count = 0;
        if (synced_ids != '') {
            completed_count = JSON.parse(synced_ids).length;
        }
        $('#omni_sync_bt').html("Syncing " + completed_count + " products")
        $.ajax({
            type: 'POST',
            url: omniversepricing_ajax_url,
            dataType: 'html',
            data: {
                controller: 'AdminAjaxOmniverse',
                action: 'OmniDataSync',
                start: start,
                end: $end,
                price_type: price_type,
                call_type: call_type,
                synced_ids: synced_ids,
                ajax: true
            },
            success: function (data) {
                var response = JSON.parse(data);
                if (!stop_sync) {
                    if (response.start != 0) {
                        if (response.success == 1) {
                            call_sync_ajax(response.start, $end, price_type, 1, JSON.stringify(response.synced_ids))
                        } else {
                            call_sync_ajax(response.start, $end, price_type, 2, JSON.stringify(response.synced_ids))
                        }
                    } else {

                        completed_count = response.synced_ids.length
                        console.log(completed_count)
                        $(".omni-sync-loader").hide();
                        $("#omni_sync_stop").addClass("hidden");
                        $('#omni_sync_bt').html("Sync completed " + completed_count + " products")
                    }
                } else {
                    $(".omni-sync-loader").hide();
                    $("#omni_sync_stop").addClass("hidden");
                    $('#omni_sync_bt').html("Sync Stopped at " + response.start)
                }
            }
        });
    }
});
