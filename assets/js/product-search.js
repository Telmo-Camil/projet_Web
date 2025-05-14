document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search');
    const searchBtn = document.getElementById('searchBtn');
    const categoryFilter = document.getElementById('categoryFilter');

    // Fonction de recherche
    function searchProducts() {
        const searchTerm = searchInput.value;
        const categoryId = categoryFilter.value;
        
        window.location.href = `index.php?uri=gestion-produit&search=${encodeURIComponent(searchTerm)}&category=${categoryId}`;
    }

    // Écouteur d'événements pour le bouton de recherche
    searchBtn.addEventListener('click', function(e) {
        e.preventDefault();
        searchProducts();
    });

    // Écouteur d'événements pour la touche Entrée dans le champ de recherche
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchProducts();
        }
    });

    // Écouteur d'événements pour le filtre de catégorie
    categoryFilter.addEventListener('change', function() {
        searchProducts();
    });
});