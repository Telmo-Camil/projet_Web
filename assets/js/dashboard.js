document.addEventListener('DOMContentLoaded', function() {
    const chartCanvas = document.getElementById('salesChart');
    if (!chartCanvas) return;

    const ctx = chartCanvas.getContext('2d');
    const statsData = JSON.parse(chartCanvas.dataset.stats || '[]');
    
    if (statsData.length === 0) return;

    const labels = statsData.map(item => {
        const [year, month] = item.mois.split('-');
        const date = new Date(year, month - 1);
        return date.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
    });

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Nombre de commandes',
                data: statsData.map(item => item.nombre_commandes),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderWidth: 2,
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Évolution des commandes sur 6 mois',
                    font: {
                        size: 16
                    }
                },
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
});