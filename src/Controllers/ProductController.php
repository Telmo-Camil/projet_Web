<?php

namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\CategoryModel;
use Twig\Environment;
use PDO;

class ProductController
{
    private $twig;
    private $productModel;
    private $categoryModel;

    public function __construct(Environment $twig, PDO $db)
    {
        $this->twig = $twig;
        $this->productModel = new ProductModel($db);
        $this->categoryModel = new CategoryModel($db);
    }

    public function index()
    {
        try {
            $search = $_GET['search'] ?? '';
            $categoryId = $_GET['category'] ?? 'all';
            
            // Récupérer les produits filtrés
            $products = $this->productModel->getFilteredProducts($search, $categoryId);
            
            // Récupérer toutes les catégories pour le filtre
            $categories = $this->categoryModel->getAllCategories();
            
            echo $this->twig->render('produit.html.twig', [
                'products' => $products,
                'categories' => $categories,
                'selectedCategory' => $categoryId,
                'search' => $search,
                'current_page' => 'gestion-produit'
            ]);
        } catch (\Exception $e) {
            error_log("Erreur: " . $e->getMessage());
            echo $this->twig->render('produit.html.twig', [
                'error_message' => "Une erreur est survenue lors du chargement des produits"
            ]);
        }
    }

    public function search()
    {
        try {
            $search = $_GET['search'] ?? '';
            $products = $this->productModel->searchProducts($search);
            echo $this->twig->render('produit.html.twig', [
                'products' => $products,
                'search' => $search
            ]);
        } catch (\Exception $e) {
            error_log($e->getMessage());
            echo $this->twig->render('error.html.twig', [
                'message' => 'Une erreur est survenue lors de la recherche.'
            ]);
        }
    }

    public function filter()
    {
        try {
            $category = $_GET['category'] ?? 'all';
            $priceRange = $_GET['price'] ?? 'all';
            $supplier = $_GET['supplier'] ?? 'all';
            $stock = $_GET['stock'] ?? 'all';

            $products = $this->productModel->filterProducts($category, $priceRange, $supplier, $stock);
            echo $this->twig->render('produit.html.twig', [
                'products' => $products,
                'filters' => [
                    'category' => $category,
                    'price' => $priceRange,
                    'supplier' => $supplier,
                    'stock' => $stock
                ]
            ]);
        } catch (\Exception $e) {
            error_log($e->getMessage());
            echo $this->twig->render('error.html.twig', [
                'message' => 'Une erreur est survenue lors du filtrage.'
            ]);
        }
    }
}