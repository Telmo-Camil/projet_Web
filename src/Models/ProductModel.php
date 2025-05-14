<?php

namespace App\Models;

use PDO;

class ProductModel 
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAllProducts()
    {
        try {
            $query = "SELECT *, product.nom as product_name, categories.nom as category_name,
            supplier.nom AS supplier_name
            FROM product 
            JOIN categories ON product.categories_id = categories.id
            JOIN supplier on product.supplier_id = supplier.id
            ";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            // Debug: Vérifier le nombre de résultats
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("ProductModel->getAllProducts() : " . count($results) . " produits trouvés");
            
            return $results;
        } catch (\PDOException $e) {
            error_log("Erreur dans ProductModel->getAllProducts(): " . $e->getMessage());
            throw $e;
        }
    }

    public function getProductById($id)
    {
        $query = "SELECT * FROM product WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function searchProducts($search)
    {
        $query = "SELECT * FROM product WHERE name LIKE :search OR category LIKE :search";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['search' => "%$search%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function filterProducts($category = 'all', $priceRange = 'all', $supplier = 'all', $stock = 'all')
    {
        $conditions = [];
        $params = [];

        if ($category !== 'all') {
            $conditions[] = "category = :category";
            $params['category'] = $category;
        }

        if ($priceRange !== 'all') {
            switch ($priceRange) {
                case 'low':
                    $conditions[] = "price < 50";
                    break;
                case 'medium':
                    $conditions[] = "price BETWEEN 50 AND 100";
                    break;
                case 'high':
                    $conditions[] = "price > 100";
                    break;
            }
        }

        if ($supplier !== 'all') {
            $conditions[] = "supplier_name = :supplier";
            $params['supplier'] = $supplier;
        }

        if ($stock === 'stock') {
            $conditions[] = "stock_quantity > 0";
        } elseif ($stock === 'rupture') {
            $conditions[] = "stock_quantity = 0";
        }

        $query = "SELECT * FROM product";
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProductsByCategory($categoryId)
    {
        try {
            $query = "SELECT p.*, 
                         p.nom as product_name,
                         c.nom as category_name,
                         s.nom as supplier_name
                      FROM product p
                      LEFT JOIN categories c ON p.categories_id = c.id
                      LEFT JOIN supplier s ON p.supplier_id = s.id
                      WHERE p.categories_id = :category_id";
                      
            $stmt = $this->db->prepare($query);
            $stmt->execute(['category_id' => $categoryId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Erreur dans getProductsByCategory: " . $e->getMessage());
            throw $e;
        }
    }

    public function getFilteredProducts($search = '', $categoryId = 'all')
    {
        $params = [];
        $conditions = [];
        
        $query = "SELECT 
                    p.*,
                    c.nom as category_name,
                    s.nom as supplier_name
                FROM product p
                LEFT JOIN categories c ON p.categories_id = c.id
                LEFT JOIN supplier s ON p.supplier_id = s.id
                WHERE 1=1";

        if (!empty($search)) {
            $conditions[] = "(p.nom LIKE :search OR c.nom LIKE :search OR s.nom LIKE :search)";
            $params['search'] = "%$search%";
        }

        if ($categoryId !== 'all') {
            $conditions[] = "p.categories_id = :category_id";
            $params['category_id'] = $categoryId;
        }

        if (!empty($conditions)) {
            $query .= " AND " . implode(" AND ", $conditions);
        }

        $query .= " ORDER BY p.nom";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}