function filterOrders() {
    const statusFilter = document.getElementById('statusFilter').value;
    const categoryFilter = document.getElementById('categoryFilter').value;
    const rows = document.querySelectorAll('table tbody tr');
    const today = new Date().toISOString().split('T')[0];

    rows.forEach(row => {
        const deliveryDate = row.querySelector('td:nth-child(7)').dataset.date;
        const categoryId = row.querySelector('td:nth-child(3)').dataset.categoryId;
        const isDelivered = deliveryDate <= today;
        const status = isDelivered ? 'livree' : 'en_attente';

        let showRow = true;

        if (statusFilter !== 'all' && status !== statusFilter) {
            showRow = false;
        }

        if (categoryFilter !== 'all' && categoryId !== categoryFilter) {
            showRow = false;
        }

        row.style.display = showRow ? '' : 'none';
    });
}