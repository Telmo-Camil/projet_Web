<?php

namespace App\Controllers;

use PDO;
use Twig\Environment;

class DashboardController
{
    private $db;
    private $twig;

    public function __construct(Environment $twig, PDO $db)
    {
        $this->db = $db;
        $this->twig = $twig;
    }

    public function index()
    {
        try {
            // 1. Total des articles en stock et nombre de produits
            $queryStock = "SELECT 
                            SUM(quantite) as total_stock,
                            COUNT(*) as nombre_produits 
                         FROM product";
            $stmt = $this->db->query($queryStock);
            $stockData = $stmt->fetch(PDO::FETCH_ASSOC);

            // 2. Nombre de produits en rupture
            $queryRuptures = "SELECT COUNT(*) as ruptures 
                            FROM product 
                            WHERE quantite <= 0";
            $stmt = $this->db->query($queryRuptures);
            $rupturesData = $stmt->fetch(PDO::FETCH_ASSOC);

            // 3. Commandes en cours
            $queryCommandes = "SELECT COUNT(*) as commandes_en_cours 
                             FROM orders 
                             WHERE DATE(date_livraison) > CURDATE()";
            $stmt = $this->db->query($queryCommandes);
            $commandesData = $stmt->fetch(PDO::FETCH_ASSOC);

            // 4. Produits en alerte de stock (moins de 10 unités)
            $queryAlerte = "SELECT 
                            p.id,
                            p.nom,
                            p.quantite,
                            c.nom as category_name
                          FROM product p
                          LEFT JOIN categories c ON p.categories_id = c.id
                          WHERE p.quantite < 10
                          ORDER BY p.quantite ASC";
            $stmt = $this->db->query($queryAlerte);
            $produitsAlerte = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 5. Statistiques des commandes sur 6 mois
            $queryStats = "SELECT 
                            DATE_FORMAT(date_livraison, '%Y-%m') as mois,
                            COUNT(*) as nombre_commandes
                          FROM orders
                          WHERE date_livraison >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                          GROUP BY DATE_FORMAT(date_livraison, '%Y-%m')
                          ORDER BY mois ASC";
            $stmt = $this->db->query($queryStats);
            $statsCommandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            $query = "SELECT 
                        DATE_FORMAT(date_livraison, '%m') as mois,
                        COUNT(*) as nombre_commandes,
                        SUM(quantite * prix) as total_ventes
                    FROM orders 
                    WHERE YEAR(date_livraison) = YEAR(CURRENT_DATE)
                    GROUP BY DATE_FORMAT(date_livraison, '%m')
                    ORDER BY mois ASC";
            
            $stmt = $this->db->query($query);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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