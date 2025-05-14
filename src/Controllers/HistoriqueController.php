<?php

namespace App\Controllers;

use PDO;
use Twig\Environment;
use App\Models\HistoriqueModel;

class HistoriqueController
{
    private $twig;
    private $historiqueModel;

    public function __construct(Environment $twig, PDO $db)
    {
        $this->twig = $twig;
        $this->historiqueModel = new HistoriqueModel($db);
    }

    public function index()
    {
        try {
            $dateStart = $_GET['dateStart'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateEnd = $_GET['dateEnd'] ?? date('Y-m-d');
            $type = $_GET['type'] ?? 'all';
            $categoryId = $_GET['category'] ?? 'all';

            // Récupérer les transactions via le modèle
            $transactions = $this->historiqueModel->getTransactions(
                $dateStart,
                $dateEnd,
                $type,
                $categoryId
            );

            // Récupérer les catégories
            $categories = $this->historiqueModel->getCategories();

            // Calculer les statistiques
            $categoryStats = $this->historiqueModel->calculateCategoryStats($transactions);

            echo $this->twig->render('historique.html.twig', [
                'transactions' => $transactions,
                'categories' => $categories,
                'categoryStats' => $categoryStats,
                'dateStart' => $dateStart,
                'dateEnd' => $dateEnd,
                'type' => $type,
                'selectedCategory' => $categoryId
            ]);

        } catch (\Exception $e) {
            error_log("Erreur dans HistoriqueController->index(): " . $e->getMessage());
            echo $this->twig->render('historique.html.twig', [
                'error_message' => "Une erreur est survenue lors du chargement de l'historique"
            ]);
        }
    }
}