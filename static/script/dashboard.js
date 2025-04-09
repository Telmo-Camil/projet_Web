const ctx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(ctx, {
  type: 'bar',
  data: {
    labels: ['Jan', 'Fév', 'Mars', 'Avril', 'Mai', 'Juin','Juil','Août','Sept','Oct','Nov','Dec'],
    datasets: [{
      label: 'Ventes',
      data: [120, 190, 300, 250, 320, 400, 350, 420, 450, 500, 480, 530],
      backgroundColor: 'rgba(46, 134, 222, 0.6)',
      borderColor: 'rgba(46, 134, 222, 1)',
      borderWidth: 1
    }]
  },
  options: {
    responsive: true,
    scales: {
      y: {
        beginAtZero: true,
        }
      }
    }
  });