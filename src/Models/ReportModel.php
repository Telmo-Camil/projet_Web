<?php

namespace App\Models;

use PDO;

class ReportModel
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getCategories()
    {
        $query = "SELECT id, nom FROM categories ORDER BY nom";
        return $this->db->query($query)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getGeneratedReports()
    {
        $query = "SELECT 
            r.*,
            CASE 
                WHEN r.type = 'stock_movements' THEN 'Mouvements de stock'
                WHEN r.type = 'purchase_forecast' THEN 'Prévisions d''achats'
                WHEN r.type = 'stock_alerts' THEN 'Alertes de stock'
            END as type_label
            FROM reports r
            ORDER BY r.created_at DESC";
        return $this->db->query($query)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getGlobalStats()
    {
        $query = "SELECT 
            (SELECT COUNT(*) FROM stock_sorties 
             WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)) as recent_movements,
            (SELECT COUNT(*) FROM product WHERE quantite < 10) as products_to_order,
            CONCAT(
                CASE 
                    WHEN (
                        SELECT SUM(CASE WHEN type = 'entree' THEN quantity ELSE -quantity END)
                        FROM (
                            SELECT 'entree' as type, quantite as quantity
                            FROM orders 
                            WHERE date_livraison >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                            UNION ALL
                            SELECT 'sortie' as type, quantity
                            FROM stock_sorties
                            WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                        ) as movements
                    ) > 0 THEN '+'
                    ELSE ''
                END,
                COALESCE(
                    (
                        SELECT SUM(CASE WHEN type = 'entree' THEN quantity ELSE -quantity END)
                        FROM (
                            SELECT 'entree' as type, quantite as quantity
                            FROM orders 
                            WHERE date_livraison >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                            UNION ALL
                            SELECT 'sortie' as type, quantity
                            FROM stock_sorties
                            WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                        ) as movements
                    ), 0
                )
            ) as stock_trend";
        return $this->db->query($query)->fetch(\PDO::FETCH_ASSOC);
    }

    public function saveReport($reportData, $type, $dateStart, $dateEnd, $categories, $format, $fileName)
    {
        $query = "INSERT INTO reports (title, type, date_start, date_end, categories, format, file_path) 
                 VALUES (:title, :type, :date_start, :date_end, :categories, :format, :file_path)";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'title' => $reportData['title'],
            'type' => $type,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'categories' => json_encode($categories),
            'format' => $format,
            'file_path' => $fileName
        ]);
    }

    public function getReportById($id)
    {
        $query = "SELECT * FROM reports WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function deleteReportById($id)
    {
        $query = "DELETE FROM reports WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['id' => $id]);
    }

    // ... autres méthodes de rapport (getStockMovementsReport, getPurchaseForecastReport, etc.)
}