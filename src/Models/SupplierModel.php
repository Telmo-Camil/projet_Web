<?php

namespace App\Models;

use PDO;

class SupplierModel
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAllSuppliers()
    {
        $query = "SELECT * FROM supplier ORDER BY nom";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSupplierById($id)
    {
        $query = "SELECT * FROM supplier WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function addSupplier($data)
    {
        try {
            $query = "INSERT INTO supplier (nom, email, telephone, adresse) 
                     VALUES (:nom, :email, :telephone, :adresse)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'nom' => $data['nom'],
                'email' => $data['email'],
                'telephone' => $data['telephone'],
                'adresse' => $data['adresse']
            ]);
            
            return $this->db->lastInsertId();
        } catch (\PDOException $e) {
            error_log("Erreur dans addSupplier(): " . $e->getMessage());
            throw $e;
        }
    }

    public function deleteSupplier($id)
    {
        $query = "DELETE FROM supplier WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['id' => $id]);
    }

    public function hasOrders($id)
    {
        $query = "SELECT COUNT(*) FROM orders WHERE supplier_id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['id' => $id]);
        return $stmt->fetchColumn() > 0;
    }

    public function updateSupplier($data)
    {
        $query = "UPDATE supplier 
                 SET nom = :nom,
                     email = :email,
                     telephone = :telephone,
                     adresse = :adresse
                 WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'id' => $data['id'],
            'nom' => $data['nom'],
            'email' => $data['email'],
            'telephone' => $data['telephone'],
            'adresse' => $data['adresse']
        ]);
    }
}