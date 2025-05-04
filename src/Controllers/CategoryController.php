<?php

namespace App\Controllers;

use App\Models\CategoryModel;
use Twig\Environment;
use PDO;

class CategoryController
{
    private $twig;
    private $categoryModel;

    public function __construct(Environment $twig, PDO $db)
    {
        $this->twig = $twig;
        $this->categoryModel = new CategoryModel($db);
    }

    public function index()
    {
        try {
            $searchTerm = $_GET['search'] ?? '';
            
            if (!empty($searchTerm)) {
                $categories = $this->categoryModel->searchCategories($searchTerm);
            } else {
                $categories = $this->categoryModel->getAllCategoriesWithCount();
            }
            
            echo $this->twig->render('gestion-produit.html.twig', [
                'categories' => $categories,
                'searchTerm' => $searchTerm
            ]);
        } catch (\Exception $e) {
            error_log("Erreur dans CategoryController->index(): " . $e->getMessage());
            echo $this->twig->render('gestion-produit.html.twig', [
                'error_message' => "Erreur lors du chargement des catégories"
            ]);
        }
    }

    public function add()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $nom = $_POST['nom'] ?? '';
                if (empty($nom)) {
                    throw new \Exception("Le nom de la catégorie est requis");
                }
                
                $this->categoryModel->addCategory($nom);
                header('Location: index.php?uri=gestion-produit&success=1');
                exit;
            } catch (\Exception $e) {
                error_log("Erreur lors de l'ajout de la catégorie: " . $e->getMessage());
                echo $this->twig->render('add-category.html.twig', [
                    'error_message' => $e->getMessage()
                ]);
            }
        } else {
            echo $this->twig->render('add-category.html.twig');
        }
    }
}