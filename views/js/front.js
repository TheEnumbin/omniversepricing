$(document).ready(function () {
    console.log("hello")
    var ctx = document.getElementById('priceHistoryChart').getContext('2d');
    console.log(ctx)
    // var priceChart;

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
            success: function (data) {
                if (priceChart) priceChart.destroy();
                var response = JSON.parse(data);
                priceChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: response.labels,
                        datasets: [{
                            label: 'Price (Last 30 Days)',
                            data: response.prices,
                            borderColor: 'red',
                            backgroundColor: 'rgba(255,0,0,0.2)',
                            tension: 0.3,
                            fill: true,
                            pointRadius: 3,
                            pointBackgroundColor: 'red'
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            x: { title: { display: true, text: 'Date' } },
                            y: { title: { display: true, text: 'Price' } }
                        }
                    }
                });
            },
            error: function (err) {
                // alert('Failed to load price data.');
            }
        });
    });

    $('#closePriceChart').on('click', function () {
        $('#priceChartModal').fadeOut();
    });
});