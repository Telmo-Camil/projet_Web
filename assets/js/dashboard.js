document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('salesChart').getContext('2d');
    const monthNames = ['Jan', 'Fév', 'Mars', 'Avril', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'];
    
    // Initialiser le graphique avec des données vides
    const salesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: monthNames,
            datasets: [{
                label: 'Commandes',
                data: Array(12).fill(0),
                backgroundColor: 'rgba(46, 134, 222, 0.6)',
                borderColor: 'rgba(46, 134, 222, 1)',
                borderWidth: 1
            }, {
                label: 'Montant total (€)',
                data: Array(12).fill(0),
                backgroundColor: 'rgba(52, 152, 219, 0.6)',
                borderColor: 'rgba(52, 152, 219, 1)',
                borderWidth: 1,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Nombre de commandes'
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Montant total (€)'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });

    // Charger les données réelles
    fetch('index.php?uri=get-chart-data')
        .then(response => response.json())
        .then(data => {
            const commandesByMonth = Array(12).fill(0);
            const salesByMonth = Array(12).fill(0);

            data.forEach(item => {
                const monthIndex = parseInt(item.mois) - 1;
                commandesByMonth[monthIndex] = parseInt(item.nombre_commandes);
                salesByMonth[monthIndex] = parseFloat(item.total_ventes);
            });

            salesChart.data.datasets[0].data = commandesByMonth;
            salesChart.data.datasets[1].data = salesByMonth;
            salesChart.update();
        })
        .catch(error => {
            console.error('Erreur lors du chargement des données:', error);
        });
});