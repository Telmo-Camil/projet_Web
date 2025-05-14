<?php


namespace App\Models;

use PDO;

class RapportModel
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getGlobalStats()
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
        $stats['stock_health'] = $this->calculateStockHealth($healthRatio);
        
        return $stats;
    }

    private function calculateStockHealth($ratio)
    {
        if ($ratio <= 0.1) {
            return ['status' => 'good', 'label' => 'Bon'];
        } elseif ($ratio <= 0.3) {
            return ['status' => 'warning', 'label' => 'Attention'];
        }
        return ['status' => 'danger', 'label' => 'Critique'];
    }

    public function getLowStockProducts()
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

        $products = $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        return $this->calculateProductPredictions($products);
    }

    private function calculateProductPredictions($products)
    {
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

    public function getTopProducts()
    {
        return [
            'exits' => $this->getTopExits(),
            'entries' => $this->getTopEntries()
        ];
    }

    private function getTopExits()
    {
        $query = "SELECT 
                    p.nom,
                    SUM(s.quantity) as total_quantity
                FROM stock_sorties s
                JOIN product p ON s.product_id = p.id
                WHERE s.date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                GROUP BY p.id
                ORDER BY total_quantity DESC
                LIMIT 5";
        
        return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTopEntries()
    {
        $query = "SELECT 
                    p.nom,
                    SUM(o.quantite) as total_quantity
                FROM orders o
                JOIN product p ON o.product_name = p.nom
                WHERE o.date_livraison >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                GROUP BY p.nom
                ORDER BY total_quantity DESC
                LIMIT 5";
        
        return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStockMovementsData($dateStart, $dateEnd, $categories)
    {
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
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->formatMovementData($results);
    }

    private function formatMovementData($results)
    {
        $formatted = [
            'labels' => [],
            'entries' => [],
            'exits' => []
        ];

        foreach ($results as $row) {
            $formatted['labels'][] = date('d/m/Y', strtotime($row['movement_date']));
            $formatted['entries'][] = $row['entries'];
            $formatted['exits'][] = $row['exits'];
        }

        $stats = $this->calculateMovementStats($results);

        return [
            'title' => 'Mouvements de Stock',
            'chartType' => 'bar',
            'labels' => $formatted['labels'],
            'datasets' => [
                [
                    'label' => 'Entrées',
                    'data' => $formatted['entries'],
                    'backgroundColor' => 'rgba(72, 187, 120, 0.5)',
                    'borderColor' => 'rgba(72, 187, 120, 1)',
                    'borderWidth' => 1
                ],
                [
                    'label' => 'Sorties',
                    'data' => $formatted['exits'],
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

        return [
            'recent_movements' => count($results),
            'products_to_order' => $this->getProductsToOrderCount(),
            'stock_trend' => ($totalEntries - $totalExits)
        ];
    }

    private function getProductsToOrderCount()
    {
        return $this->db->query("SELECT COUNT(*) FROM product WHERE quantite < 10")->fetchColumn();
    }
}