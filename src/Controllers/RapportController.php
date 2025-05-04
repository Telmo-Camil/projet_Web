<?php

namespace App\Controllers;

use PDO;
use Twig\Environment;
use DateTime;

class RapportController
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
            // Statistiques globales
            $globalStats = $this->getGlobalStats();

            // Produits en alerte de stock
            $lowStockProducts = $this->getLowStockProducts();

            // Top produits
            $topProducts = $this->getTopProducts();

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

    private function getGlobalStats()
    {
        $query = "SELECT 
                    COUNT(*) as product_count,
                    SUM(quantite) as total_stock,
                    SUM(quantite * prix) as stock_value,
                    COUNT(CASE WHEN quantite < 10 THEN 1 END) as low_stock_count
                FROM product";
        
        $stmt = $this->db->query($query);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Déterminer la santé du stock
        $healthRatio = $stats['low_stock_count'] / $stats['product_count'];
        if ($healthRatio <= 0.1) {
            $stats['stock_health'] = 'good';
            $stats['stock_health_label'] = 'Bon';
        } elseif ($healthRatio <= 0.3) {
            $stats['stock_health'] = 'warning';
            $stats['stock_health_label'] = 'Attention';
        } else {
            $stats['stock_health'] = 'danger';
            $stats['stock_health_label'] = 'Critique';
        }
        
        return $stats;
    }

    private function getLowStockProducts()
    {
        $query = "SELECT 
                    p.id,
                    p.nom,
                    p.quantite,
                    p.prix,
                    c.nom as category_name,
                    10 as alert_threshold,
                    CASE 
                        WHEN p.quantite = 0 THEN 'critical'
                        WHEN p.quantite < 5 THEN 'danger'
                        WHEN p.quantite < 10 THEN 'warning'
                    END as alert_level,
                    COALESCE(
                        (SELECT AVG(s.quantity) 
                         FROM stock_sorties s 
                         WHERE s.product_id = p.id 
                         AND s.date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                        ), 0
                    ) as avg_monthly_usage
                FROM product p
                LEFT JOIN categories c ON p.categories_id = c.id
                WHERE p.quantite < 10
                ORDER BY p.quantite ASC";

        $stmt = $this->db->query($query);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculer les prévisions
        foreach ($products as &$product) {
            if ($product['avg_monthly_usage'] > 0) {
                $daysLeft = $product['quantite'] / ($product['avg_monthly_usage'] / 30);
                $product['estimated_empty_date'] = date('Y-m-d', strtotime("+$daysLeft days"));
                $product['suggested_order_quantity'] = ceil($product['avg_monthly_usage']);
            } else {
                $product['estimated_empty_date'] = null;
                $product['suggested_order_quantity'] = 10;
            }
        }

        return $products;
    }

    private function getTopProducts()
    {
        // Top sorties
        $queryExits = "SELECT 
                        p.nom,
                        SUM(s.quantity) as total_quantity
                    FROM stock_sorties s
                    JOIN product p ON s.product_id = p.id
                    WHERE s.date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                    GROUP BY p.id
                    ORDER BY total_quantity DESC
                    LIMIT 5";

        // Top entrées
        $queryEntries = "SELECT 
                        p.nom,
                        SUM(o.quantite) as total_quantity
                    FROM orders o
                    JOIN product p ON o.product_name = p.nom
                    WHERE o.date_livraison >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                    GROUP BY p.nom
                    ORDER BY total_quantity DESC
                    LIMIT 5";

        return [
            'exits' => $this->db->query($queryExits)->fetchAll(PDO::FETCH_ASSOC),
            'entries' => $this->db->query($queryEntries)->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    public function previewReport()
    {
        try {
            $type = $_GET['type'] ?? 'stock_movements';
            $dateStart = $_GET['date_start'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateEnd = $_GET['date_end'] ?? date('Y-m-d');
            $categories = $_GET['categories'] ?? 'all';

            $data = [
                'title' => '',
                'chartType' => 'line',
                'labels' => [],
                'datasets' => [],
                'stats' => []
            ];

            switch ($type) {
                case 'stock_movements':
                    $data = $this->getStockMovementsData($dateStart, $dateEnd, $categories);
                    break;
                case 'purchase_forecast':
                    $data = $this->getPurchaseForecastData($dateStart, $dateEnd, $categories);
                    break;
                case 'stock_alerts':
                    $data = $this->getStockAlertsData($categories);
                    break;
            }

            header('Content-Type: application/json');
            echo json_encode($data);
            exit;

        } catch (\Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }

    private function getStockMovementsData($dateStart, $dateEnd, $categories)
    {
        $categoryCondition = ($categories !== 'all') ? "AND p.categories_id IN ($categories)" : '';
        
        // Récupérer les mouvements par jour
        $query = "
            SELECT 
                DATE(date) as movement_date,
                SUM(CASE WHEN type = 'entree' THEN quantity ELSE 0 END) as entries,
                SUM(CASE WHEN type = 'sortie' THEN quantity ELSE 0 END) as exits
            FROM (
                SELECT date_livraison as date, 'entree' as type, quantite as quantity
                FROM orders 
                WHERE date_livraison BETWEEN :start_date1 AND :end_date1
                UNION ALL
                SELECT date, 'sortie' as type, quantity
                FROM stock_sorties
                WHERE date BETWEEN :start_date2 AND :end_date2
            ) movements
            GROUP BY DATE(date)
            ORDER BY movement_date";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'start_date1' => $dateStart,
            'end_date1' => $dateEnd,
            'start_date2' => $dateStart,
            'end_date2' => $dateEnd
        ]);
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Formater les données pour le graphique
        $labels = [];
        $entries = [];
        $exits = [];
        
        foreach ($results as $row) {
            $labels[] = date('d/m/Y', strtotime($row['movement_date']));
            $entries[] = $row['entries'];
            $exits[] = $row['exits'];
        }

        // Calculer les statistiques
        $stats = $this->calculateMovementStats($results);

        return [
            'title' => 'Mouvements de Stock',
            'chartType' => 'bar',
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Entrées',
                    'data' => $entries,
                    'backgroundColor' => 'rgba(72, 187, 120, 0.5)',
                    'borderColor' => 'rgba(72, 187, 120, 1)',
                    'borderWidth' => 1
                ],
                [
                    'label' => 'Sorties',
                    'data' => $exits,
                    'backgroundColor' => 'rgba(245, 101, 101, 0.5)',
                    'borderColor' => 'rgba(245, 101, 101, 1)',
                    'borderWidth' => 1
                ]
            ],
            'stats' => $stats
        ];
    }

    private function calculateMovementStats($results)
    {
        $totalEntries = 0;
        $totalExits = 0;

        foreach ($results as $row) {
            $totalEntries += $row['entries'];
            $totalExits += $row['exits'];
        }

        // Calculer la tendance
        $trend = $totalEntries - $totalExits;
        $trendLabel = $trend > 0 ? '+' . $trend : $trend;

        return [
            'recent_movements' => count($results),
            'products_to_order' => $this->getProductsToOrderCount(),
            'stock_trend' => $trendLabel
        ];
    }

    private function getProductsToOrderCount()
    {
        $query = "SELECT COUNT(*) FROM product WHERE quantite < 10";
        return $this->db->query($query)->fetchColumn();
    }
}