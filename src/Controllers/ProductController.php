<?php

namespace App\Controllers;

use App\Models\ProductModel;
use Twig\Environment;
use PDO;

class ProductController
{
    private $twig;
    private $productModel;

    public function __construct(Environment $twig, PDO $db)
    {
        $this->twig = $twig;
        $this->productModel = new ProductModel($db);
    }

    public function index()
    {
        try {
            // Debug: Vérifier la connexion
            error_log("Tentative de récupération des produits...");
            
            $products = $this->productModel->getAllProducts();
            
            // Debug: Afficher les produits récupérés
            error_log("Produits trouvés : " . print_r($products, true));

            echo $this->twig->render('produit.html.twig', [
                'products' => $products
            ]);
        } catch (\Exception $e) {
            error_log("Erreur dans ProductController: " . $e->getMessage());
            echo $this->twig->render('produit.html.twig', [
                'error_message' => "Erreur lors du chargement des produits: " . $e->getMessage()
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