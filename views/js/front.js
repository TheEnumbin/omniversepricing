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
*
* Don't forget to prefix your containers with your own identifier
* to avoid any conflicts with others containers.
*/
$(document).ready(function () {
    var ctx = document.getElementById('priceHistoryChart').getContext('2d');
    console.log(ctx)
    var priceChart;

    $('#openPriceChart').on('click', function () {
        $('#priceChartModal').fadeIn();

        var $id_product_for_chart = $(this).data('prd_id');
        var $attr_id = $(this).data('attr_id');
        $.ajax({
            url: omniversepricing_ajax_front_url,
            type: 'POST',
            dataType: 'json',
            data: {
                ajax: true,
                id_product: $id_product_for_chart,
                attr_id: $attr_id
            },
            success: function (response) {
                const labels = response.map(item => item.date);
                const prices = response.map(item => item.price);

                // Show modal
                $('#priceChartModal').fadeIn();

                // Destroy previous chart instance if exists
                if (window.priceChartInstance) {
                    window.priceChartInstance.destroy();
                }
                window.priceChartInstance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: omni_chart_label,
                            data: prices,
                            borderColor: chart_line_color,
                            backgroundColor: chart_bg_color,
                            tension: 0.4,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: omni_date_label
                                }
                            },
                            y: {
                                title: {
                                    display: true,
                                    text: omni_price_label
                                },
                                beginAtZero: false,
                                suggestedMax: 1000
                            }
                        }
                    }
                });
            },
            error: function () {
                alert('Error loading price data.');
            }
        });
    });

    $('.omni-close, .omni-modal').on('click', function (e) {
        if ($(e.target).is('.omni-close') || $(e.target).is('.omni-modal')) {
            $('#priceChartModal').fadeOut();
        }
    });
});