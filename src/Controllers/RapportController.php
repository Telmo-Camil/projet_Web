<?php

namespace App\Controllers;

use PDO;
use Twig\Environment;
use App\Models\RapportModel;

class RapportController
{
    private $twig;
    private $rapportModel;

    public function __construct(Environment $twig, PDO $db)
    {
        $this->twig = $twig;
        $this->rapportModel = new RapportModel($db);
    }

    public function index()
    {
        try {
            $globalStats = $this->rapportModel->getGlobalStats();
            $lowStockProducts = $this->rapportModel->getLowStockProducts();
            $topProducts = $this->rapportModel->getTopProducts();

            echo $this->twig->render('rapport.html.twig', [
                'globalStats' => $globalStats,
                'lowStockProducts' => $lowStockProducts,
                'topExits' => $topProducts['exits'],
                'topEntries' => $topProducts['entries']
            ]);

        } catch (\Exception $e) {
            error_log("Erreur dans RapportController->index(): " . $e->getMessage());
            echo $this->twig->render('rapport.html.twig', [
                'error_message' => "Une erreur est survenue lors de la génération du rapport"
            ]);
        }
    }

    public function previewReport()
    {
        try {
            $type = $_GET['type'] ?? 'stock_movements';
            $dateStart = $_GET['date_start'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateEnd = $_GET['date_end'] ?? date('Y-m-d');
            $categories = $_GET['categories'] ?? 'all';

            $data = $this->rapportModel->getStockMovementsData($dateStart, $dateEnd, $categories);

            header('Content-Type: application/json');
            echo json_encode($data);
            exit;

        } catch (\Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
}