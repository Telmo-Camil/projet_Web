<?php

namespace App\Models;

use PDO;

class DashboardModel 
{
    private $db;

    public function __construct(PDO $db) 
    {
        $this->db = $db;
    }

    public function getStockStats() 
    {
        $query = "SELECT 
                    SUM(quantite) as total_stock,
                    COUNT(*) as nombre_produits 
                 FROM product";
        $stmt = $this->db->query($query);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getOutOfStockCount() 
    {
        $query = "SELECT COUNT(*) as ruptures 
                FROM product 
                WHERE quantite <= 0";
        $stmt = $this->db->query($query);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPendingOrders() 
    {
        $query = "SELECT COUNT(*) as commandes_en_cours 
                 FROM orders 
                 WHERE DATE(date_livraison) > CURDATE()";
        $stmt = $this->db->query($query);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getLowStockProducts() 
    {
        $query = "SELECT 
                    p.id,
                    p.nom,
                    p.quantite,
                    c.nom as category_name
                  FROM product p
                  LEFT JOIN categories c ON p.categories_id = c.id
                  WHERE p.quantite < 10
                  ORDER BY p.quantite ASC";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrderStats() 
    {
        $query = "SELECT 
                    DATE_FORMAT(date_livraison, '%Y-%m') as mois,
                    COUNT(*) as nombre_commandes
                  FROM orders
                  WHERE date_livraison >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                  GROUP BY DATE_FORMAT(date_livraison, '%Y-%m')
                  ORDER BY mois ASC";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getChartData() 
    {
        $query = "SELECT 
                    DATE_FORMAT(date_livraison, '%m') as mois,
                    COUNT(*) as nombre_commandes,
                    SUM(quantite * prix) as total_ventes
                FROM orders 
                WHERE YEAR(date_livraison) = YEAR(CURRENT_DATE)
                GROUP BY DATE_FORMAT(date_livraison, '%m')
                ORDER BY mois ASC";
        
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}