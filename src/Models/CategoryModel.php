<?php

namespace App\Models;

use PDO;

class CategoryModel 
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAllCategories()
    {
        try {
            $query = "SELECT * FROM categories ORDER BY nom";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Debug: Log the SQL results
            error_log("SQL Query results: " . print_r($results, true));
            
            return $results;
        } catch (\PDOException $e) {
            error_log("Erreur dans getAllCategories(): " . $e->getMessage());
            throw $e;
        }
    }

    public function addCategory($nom)
    {
        try {
            $query = "INSERT INTO categories (nom) VALUES (:nom)";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['nom' => $nom]);
            return $this->db->lastInsertId();
        } catch (\PDOException $e) {
            error_log("Erreur dans addCategory(): " . $e->getMessage());
            throw $e;
        }
    }

    public function deleteCategory($id)
    {
        try {
            $query = "DELETE FROM categories WHERE id = :id";
            $stmt = $this->db->prepare($query);
            return $stmt->execute(['id' => $id]);
        } catch (\PDOException $e) {
            error_log("Erreur dans deleteCategory(): " . $e->getMessage());
            throw $e;
        }
    }

    public function updateCategory($id, $nom)
    {
        try {
            $query = "UPDATE categories SET nom = :nom WHERE id = :id";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'id' => $id,
                'nom' => $nom
            ]);
        } catch (\PDOException $e) {
            error_log("Erreur dans updateCategory(): " . $e->getMessage());
            throw $e;
        }
    }

    public function getCategoryById($id)
    {
        try {
            $query = "SELECT * FROM categories WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Erreur dans getCategoryById(): " . $e->getMessage());
            throw $e;
        }
    }

    public function searchCategories($searchTerm)
    {
        try {
            $query = "SELECT * FROM categories WHERE nom LIKE :search ORDER BY nom";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['search' => "%$searchTerm%"]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Erreur dans searchCategories(): " . $e->getMessage());
            throw $e;
        }
    }

    public function categoryExists($nom)
    {
        try {
            $query = "SELECT COUNT(*) FROM categories WHERE nom = :nom";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['nom' => $nom]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            error_log("Erreur dans categoryExists(): " . $e->getMessage());
            throw $e;
        }
    }

    public function getAllCategoriesWithCount()
    {
        try {
            $query = "SELECT c.*, COUNT(p.id) as product_count 
                     FROM categories c 
                     LEFT JOIN product p ON c.id = p.categories_id 
                     GROUP BY c.id 
                     ORDER BY c.nom";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Erreur dans getAllCategoriesWithCount(): " . $e->getMessage());
            throw $e;
        }
    }
}

