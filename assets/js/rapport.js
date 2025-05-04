document.addEventListener('DOMContentLoaded', function() {
    const reportTypeSelect = document.getElementById('report_type');
    const dateStart = document.getElementById('date_start');
    const dateEnd = document.getElementById('date_end');
    const categoriesSelect = document.getElementById('categories');
    let previewChart = null;

    // Fonction pour mettre à jour l'aperçu
    function updatePreview() {
        const params = new URLSearchParams({
            type: reportTypeSelect.value,
            date_start: dateStart.value,
            date_end: dateEnd.value,
            categories: Array.from(categoriesSelect.selectedOptions).map(opt => opt.value).join(',')
        });

        fetch(`index.php?uri=preview-report&${params.toString()}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur réseau');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }

                // Mettre à jour le graphique
                if (previewChart) {
                    previewChart.destroy();
                }
                
                const ctx = document.getElementById('previewChart').getContext('2d');
                previewChart = new Chart(ctx, {
                    type: data.chartType || 'line',
                    data: {
                        labels: data.labels,
                        datasets: data.datasets
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: data.title
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });

                // Mise à jour des statistiques
                if (data.stats) {
                    updateStats(data.stats);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                // Afficher un message d'erreur à l'utilisateur
                document.querySelector('.stats-summary').innerHTML = `
                    <div class="error-message">
                        Une erreur est survenue lors du chargement des données
                    </div>
                `;
            });
    }

    // Mettre à jour les statistiques affichées
    function updateStats(stats) {
        document.querySelector('.stats-summary').innerHTML = `
            <div class="stat-card">
                <h3>Mouvements récents</h3>
                <p class="stat-value">${stats.recent_movements}</p>
                <p class="stat-label">derniers 30 jours</p>
            </div>
            <div class="stat-card">
                <h3>Produits à commander</h3>
                <p class="stat-value">${stats.products_to_order}</p>
                <p class="stat-label">sous le seuil d'alerte</p>
            </div>
            <div class="stat-card">
                <h3>Tendance du stock</h3>
                <p class="stat-value">${stats.stock_trend}</p>
                <p class="stat-label">évolution mensuelle</p>
            </div>
        `;
    }

    // Événements pour mettre à jour l'aperçu
    reportTypeSelect.addEventListener('change', updatePreview);
    dateStart.addEventListener('change', updatePreview);
    dateEnd.addEventListener('change', updatePreview);
    categoriesSelect.addEventListener('change', updatePreview);

    // Charger l'aperçu initial
    updatePreview();

    // Charger les données pour les graphiques
    fetch('index.php?uri=get-chart-data')
        .then(response => response.json())
        .then(data => {
            createStockMovementsChart(data.movements);
            createForecastChart(data.forecast);
        })
        .catch(error => console.error('Erreur:', error));
});

function createStockMovementsChart(data) {
    const ctx = document.getElementById('stockMovementsChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Entrées',
                data: data.entries,
                backgroundColor: 'rgba(72, 187, 120, 0.5)',
                borderColor: 'rgba(72, 187, 120, 1)',
                borderWidth: 1
            }, {
                label: 'Sorties',
                data: data.exits,
                backgroundColor: 'rgba(245, 101, 101, 0.5)',
                borderColor: 'rgba(245, 101, 101, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quantité'
                    }
                }
            }
        }
    });
}

function createForecastChart(data) {
    const ctx = document.getElementById('purchaseForecastChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Stock prévu',
                data: data.forecast,
                borderColor: 'rgba(66, 153, 225, 1)',
                backgroundColor: 'rgba(66, 153, 225, 0.1)',
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Niveau de stock'
                    }
                }
            }
        }
    });
}