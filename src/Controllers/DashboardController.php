<?php

namespace App\Controllers;

use Twig\Environment;
use PDO;

class DashboardController
{
    private $twig;
    private $db;

    public function __construct(Environment $twig, PDO $db)
    {
        $this->twig = $twig;
        $this->db = $db;
    }

    public function index()
    {
        try {
            // Get total products and stock
            $queryStock = "SELECT COUNT(id) as nombre_produits, 
                                 SUM(quantite) as total_stock 
                          FROM product";
            $stmtStock = $this->db->query($queryStock);
            $stockInfo = $stmtStock->fetch(PDO::FETCH_ASSOC);

            // Get products out of stock
            $queryRupture = "SELECT COUNT(*) as total 
                            FROM product 
                            WHERE quantite = 0";
            $stmtRupture = $this->db->query($queryRupture);
            $ruptures = $stmtRupture->fetch(PDO::FETCH_ASSOC)['total'];

            // Get ongoing orders with corrected status check
            $queryCommandes = "SELECT COUNT(*) as total 
                             FROM orders 
                             WHERE statut NOT IN ('livrée', 'annulée')";
            $stmtCommandes = $this->db->query($queryCommandes);
            $commandesEnCours = $stmtCommandes->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

            // Get low stock alerts
            $queryAlerte = "SELECT p.*, c.nom as category_name 
                           FROM product p 
                           LEFT JOIN categories c ON p.categories_id = c.id 
                           WHERE p.quantite <= 5 
                           ORDER BY p.quantite ASC";
            $stmtAlerte = $this->db->query($queryAlerte);
            $produitsAlerte = $stmtAlerte->fetchAll(PDO::FETCH_ASSOC);

            // Get order statistics
            $queryStats = "SELECT 
                            DATE_FORMAT(date_commande, '%Y-%m') as mois,
                            COUNT(*) as nombre_commandes,
                            SUM(prix * quantite) as montant_total
                          FROM orders
                          WHERE date_commande >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                          GROUP BY DATE_FORMAT(date_commande, '%Y-%m')
                          ORDER BY mois ASC";
            $stmtStats = $this->db->query($queryStats);
            $statsCommandes = $stmtStats->fetchAll(PDO::FETCH_ASSOC);

            // Debug des données avant de les passer au template
            error_log('Stats commandes: ' . json_encode($statsCommandes));

            echo $this->twig->render('dashboard.html.twig', [
                'nombreProduits' => $stockInfo['nombre_produits'] ?? 0,
                'totalStock' => $stockInfo['total_stock'] ?? 0,
                'ruptures' => $ruptures ?? 0,
                'commandesEnCours' => $commandesEnCours,
                'produitsAlerte' => $produitsAlerte ?? [],
                'statsCommandes' => $statsCommandes // Assurez-vous que cette variable contient des données
            ]);

        } catch (\Exception $e) {
            error_log("Erreur dans DashboardController->index(): " . $e->getMessage());
            echo $this->twig->render('dashboard.html.twig', [
                'error_message' => "Une erreur est survenue lors du chargement du tableau de bord"
            ]);
        }
    }
}