<?php

namespace App\Controllers;

use PDO;
use Twig\Environment;

class HistoriqueController
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
            $dateStart = $_GET['dateStart'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateEnd = $_GET['dateEnd'] ?? date('Y-m-d');
            $type = $_GET['type'] ?? 'all';
            $categoryId = $_GET['category'] ?? 'all';

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

            $query = "
                SELECT t.*
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

            // Filtrer par type si nécessaire
            if ($type !== 'all') {
                $transactions = array_filter($transactions, function($t) use ($type) {
                    return $t['type'] === $type;
                });
            }

            // Récupérer les catégories pour le filtre
            $queryCategories = "SELECT id, nom, 
                (SELECT COUNT(*) 
                 FROM product 
                 WHERE categories_id = categories.id) as product_count
                FROM categories 
                ORDER BY nom";
            $stmt = $this->db->query($queryCategories);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Statistiques par catégorie
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