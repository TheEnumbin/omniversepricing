$(document).ready(function () {
    var ctx = document.getElementById('priceHistoryChart').getContext('2d');
    var priceChart;

    $('#openPriceChart').on('click', function () {
        $('#priceChartModal').fadeIn();

        // Example: Generate fake 30 days price data
        var labels = [];
        var prices = [];
        var today = new Date();

        for (var i = 29; i >= 0; i--) {
            var d = new Date(today);
            d.setDate(today.getDate() - i);
            labels.push(d.toISOString().split('T')[0]); // Format YYYY-MM-DD
            prices.push((Math.random() * (120 - 80) + 80).toFixed(2)); // Random price between 80-120
        }

        if (priceChart) {
            priceChart.destroy(); // Destroy previous instance
        }

        priceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Price (Last 30 Days)',
                    data: prices,
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
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Price'
                        }
                    }
                }
            }
        });
    });

    $('#closePriceChart').on('click', function () {
        $('#priceChartModal').fadeOut();
    });
});