
 document.addEventListener("DOMContentLoaded", function () {
    const searchInput = document.getElementById("search");
    const searchBtn = document.getElementById("searchBtn");
    const categoryFilter = document.getElementById("categoryFilter");
    const productCards = document.querySelectorAll(".product-card");

    function filterProducts() {
      const searchValue = searchInput.value.toLowerCase();
      const selectedCategory = categoryFilter.value;

      productCards.forEach(card => {
        const name = card.querySelector("h4").textContent.toLowerCase();
        const categoryText = card.querySelector("p").textContent.toLowerCase();

        const matchSearch = name.includes(searchValue);
        const matchCategory = selectedCategory === "all" || categoryText.includes(selectedCategory);

        if (matchSearch && matchCategory) {
          card.style.display = "flex";
        } else {
          card.style.display = "none";
        }
      });
    }

    searchBtn.addEventListener("click", filterProducts);
    categoryFilter.addEventListener("change", filterProducts);
  });

