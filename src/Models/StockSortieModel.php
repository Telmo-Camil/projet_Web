<?php


namespace App\Models;

use PDO;

class StockSortieModel
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAvailableProducts()
    {
        $query = "SELECT id, nom, quantite FROM product WHERE quantite > 0";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSortiesHistory()
    {
        $query = "SELECT 
                    s.date,
                    p.nom as product_name,
                    s.quantity,
                    s.reason,
                    s.remaining_stock
                FROM stock_sorties s
                JOIN product p ON s.product_id = p.id
                ORDER BY s.date DESC";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addSortie($productId, $quantity, $reason)
    {
        try {
            $this->db->beginTransaction();

            // Vérifier le stock disponible
            $stmt = $this->db->prepare("SELECT quantite FROM product WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product || $product['quantite'] < $quantity) {
                throw new \Exception("Stock insuffisant");
            }

            // Mettre à jour le stock
            $stmt = $this->db->prepare(
                "UPDATE product SET quantite = quantite - :quantity WHERE id = :id"
            );
            $stmt->execute([
                'quantity' => $quantity,
                'id' => $productId
            ]);

            // Enregistrer la sortie
            $stmt = $this->db->prepare(
                "INSERT INTO stock_sorties (
                    product_id, 
                    quantity, 
                    reason, 
                    date,
                    remaining_stock
                ) VALUES (
                    :product_id,
                    :quantity,
                    :reason,
                    NOW(),
                    :remaining_stock
                )"
            );
            $stmt->execute([
                'product_id' => $productId,
                'quantity' => $quantity,
                'reason' => $reason,
                'remaining_stock' => $product['quantite'] - $quantity
            ]);

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}