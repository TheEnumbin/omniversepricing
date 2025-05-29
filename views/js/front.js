$(document).ready(function () {
    console.log("hello")
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
                            label: 'Price (Last 30 Days)',
                            data: prices,
                            borderColor: 'rgba(75, 192, 192, 1)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
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
                                    text: 'Date'
                                }
                            },
                            y: {
                                title: {
                                    display: true,
                                    text: 'Price'
                                },
                                beginAtZero: false
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