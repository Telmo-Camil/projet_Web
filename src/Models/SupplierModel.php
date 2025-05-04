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
        try {
            $query = "SELECT * FROM supplier ORDER BY nom";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Erreur dans getAllSuppliers(): " . $e->getMessage());
            throw $e;
        }
    }

    public function getSupplierById($id)
    {
        try {
            $query = "SELECT * FROM supplier WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Erreur dans getSupplierById(): " . $e->getMessage());
            throw $e;
        }
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
}