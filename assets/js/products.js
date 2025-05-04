function filterByCategory(categoryId) {
    const url = new URL(window.location.href);
    url.searchParams.set('category', categoryId);
    window.location.href = url.toString();
}