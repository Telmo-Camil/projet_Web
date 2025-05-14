<?php

namespace App\Controllers;

use PDO;
use Twig\Environment;
use App\Models\StockSortieModel;

class StockSortieController
{
    private $twig;
    private $stockSortieModel;

    public function __construct(Environment $twig, PDO $db)
    {
        $this->twig = $twig;
        $this->stockSortieModel = new StockSortieModel($db);
    }

    public function index()
    {
        try {
            $products = $this->stockSortieModel->getAvailableProducts();
            $sorties = $this->stockSortieModel->getSortiesHistory();

            echo $this->twig->render('gestion-sortie.html.twig', [
                'products' => $products,
                'sorties' => $sorties,
                'current_page' => 'gestion-sortie'
            ]);
        } catch (\Exception $e) {
            error_log("Erreur dans StockSortieController->index(): " . $e->getMessage());
            echo $this->twig->render('gestion-sortie.html.twig', [
                'error_message' => "Une erreur est survenue lors du chargement de la page",
                'current_page' => 'gestion-sortie'
            ]);
        }
    }

    public function addSortie()
    {
        try {
            $productId = $_POST['product_id'] ?? null;
            $quantity = $_POST['quantity'] ?? null;
            $reason = $_POST['reason'] ?? null;

            if (!$productId || !$quantity || !$reason) {
                throw new \Exception("Tous les champs sont obligatoires");
            }

            $this->stockSortieModel->addSortie($productId, $quantity, $reason);
            $_SESSION['success'] = "Sortie enregistrée avec succès";

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: index.php?uri=gestion-sortie');
        exit;
    }
}