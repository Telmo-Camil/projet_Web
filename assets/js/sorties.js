document.addEventListener('DOMContentLoaded', function() {
    const productSelect = document.getElementById('product_id');
    const quantityInput = document.getElementById('quantity');
    const stockInfo = document.querySelector('.stock-info');
    
    function updateStockInfo() {
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        const currentStock = selectedOption.dataset.stock;
        
        if (currentStock) {
            stockInfo.textContent = `Stock disponible : ${currentStock}`;
            quantityInput.max = currentStock;
        }
    }
    
    productSelect.addEventListener('change', updateStockInfo);
    
    document.querySelector('.sortie-form').addEventListener('submit', function(e) {
        const quantity = parseInt(quantityInput.value);
        const currentStock = parseInt(productSelect.options[productSelect.selectedIndex].dataset.stock);
        
        if (quantity > currentStock) {
            e.preventDefault();
            alert('La quantité demandée dépasse le stock disponible');
        }
    });
});