<?php


namespace App\Models;

use PDO;

class HistoriqueModel
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getTransactions($dateStart, $dateEnd, $type = 'all', $categoryId = 'all')
    {
        $params = [
            'start_date1' => $dateStart,
            'end_date1' => $dateEnd . ' 23:59:59',
            'start_date2' => $dateStart,
            'end_date2' => $dateEnd . ' 23:59:59'
        ];

        $categoryCondition = '';
        if ($categoryId !== 'all') {
            $categoryCondition = "AND c.id = :category_id";
            $params['category_id'] = $categoryId;
        }
    
    
        $query = "SELECT t.*
            FROM (
                SELECT 
                    o.date_livraison as date,
                    'entree' as type,
                    p.nom as product_name,
                    c.nom as category_name,
                    c.id as category_id,
                    o.quantite as quantity,
                    'Livraison' as reason,
                    p.quantite as stock_after
                FROM orders o
                JOIN product p ON o.product_name = p.nom
                JOIN categories c ON p.categories_id = c.id
                WHERE o.date_livraison BETWEEN :start_date1 AND :end_date1
                AND o.stock_added = 1
                {$categoryCondition}

                UNION ALL

                SELECT 
                    s.date,
                    'sortie' as type,
                    p.nom as product_name,
                    c.nom as category_name,
                    c.id as category_id,
                    s.quantity,
                    s.reason,
                    s.remaining_stock as stock_after
                FROM stock_sorties s
                JOIN product p ON s.product_id = p.id
                JOIN categories c ON p.categories_id = c.id
                WHERE s.date BETWEEN :start_date2 AND :end_date2
                {$categoryCondition}
            ) t
            ORDER BY t.date DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($type !== 'all') {
            $transactions = array_filter($transactions, function($t) use ($type) {
                return $t['type'] === $type;
            });
        }

        return $transactions;
    }

    public function getCategories()
    {
        $query = "SELECT id, nom, 
            (SELECT COUNT(*) 
             FROM product 
             WHERE categories_id = categories.id) as product_count
            FROM categories 
            ORDER BY nom";
        
        return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function calculateCategoryStats($transactions)
    {
        $categoryStats = [];
        foreach ($transactions as $transaction) {
            $catId = $transaction['category_id'];
            if (!isset($categoryStats[$catId])) {
                $categoryStats[$catId] = [
                    'name' => $transaction['category_name'],
                    'entries' => 0,
                    'exits' => 0,
                    'total_in' => 0,
                    'total_out' => 0
                ];
            }
            
            if ($transaction['type'] === 'entree') {
                $categoryStats[$catId]['entries']++;
                $categoryStats[$catId]['total_in'] += $transaction['quantity'];
            } else {
                $categoryStats[$catId]['exits']++;
                $categoryStats[$catId]['total_out'] += $transaction['quantity'];
            }
        }
        
        return $categoryStats;
    }
}