<?php

namespace App\Controllers;

use PDO;
use Twig\Environment;
use App\Models\DashboardModel;

class DashboardController
{
    private $db;
    private $twig;
    private $dashboardModel;

    public function __construct(Environment $twig, PDO $db)
    {
        $this->db = $db;
        $this->twig = $twig;
        $this->dashboardModel = new DashboardModel($db);
    }

    public function index()
    {
        try {
            // Récupération des données via le model
            $stockData = $this->dashboardModel->getStockStats();
            $rupturesData = $this->dashboardModel->getOutOfStockCount();
            $commandesData = $this->dashboardModel->getPendingOrders();
            $produitsAlerte = $this->dashboardModel->getLowStockProducts();
            $statsCommandes = $this->dashboardModel->getOrderStats();

            echo $this->twig->render('dashboard.html.twig', [
                'totalStock' => $stockData['total_stock'] ?? 0,
                'nombreProduits' => $stockData['nombre_produits'] ?? 0,
                'ruptures' => $rupturesData['ruptures'] ?? 0,
                'commandesEnCours' => $commandesData['commandes_en_cours'] ?? 0,
                'produitsAlerte' => $produitsAlerte,
                'statsCommandes' => $statsCommandes
            ]);

        } catch (\Exception $e) {
            error_log("Erreur dans DashboardController->index(): " . $e->getMessage());
            echo $this->twig->render('dashboard.html.twig', [
                'error_message' => "Une erreur est survenue lors du chargement du tableau de bord"
            ]);
        }
    }

    public function getChartData()
    {
        try {
            $data = $this->dashboardModel->getChartData();
            
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