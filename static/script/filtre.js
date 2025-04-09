const searchBtn = document.getElementById('searchBtn');
const filterBtn = document.getElementById('filterBtn');
const filtersPanel = document.getElementById('filtersPanel');

filterBtn.addEventListener('click', () => {
  filtersPanel.style.display = (filtersPanel.style.display === 'none' || filtersPanel.style.display === '') ? 'block' : 'none';
});

searchBtn.addEventListener('click', () => {
  const searchValue = searchInput.value.toLowerCase();
  const selectedCategory = document.getElementById('categoryFilter').value;

  // Appelle ici ta fonction de filtre (comme dans l’exemple précédent)
  filterArticles(searchValue, selectedCategory);
});

function filterArticles(searchValue, selectedCategory) {
  const articles = document.querySelectorAll('#articleList li');

  articles.forEach(article => {
    const name = article.dataset.name.toLowerCase();
    const category = article.dataset.category;

    const matchSearch = name.includes(searchValue);
    const matchCategory = selectedCategory === 'all' || selectedCategory === category;

    article.style.display = (matchSearch && matchCategory) ? '' : 'none';
  });
}