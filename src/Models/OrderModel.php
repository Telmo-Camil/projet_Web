<?php

namespace App\Models;

use PDO;

class OrderModel
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function createOrder($data)
    {
        try {
            $this->db->beginTransaction();

            $query = "INSERT INTO orders (
                product_name, 
                prix, 
                quantite, 
                categories_id, 
                supplier_id, 
                date_livraison, 
                users_id
            ) VALUES (
                :product_name, 
                :prix, 
                :quantite, 
                :categories_id, 
                :supplier_id, 
                :date_livraison, 
                :users_id
            )";

            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                'product_name' => $data['product_name'],
                'prix' => $data['prix'],
                'quantite' => $data['quantite'],
                'categories_id' => $data['category_id'],
                'supplier_id' => $data['supplier_id'],
                'date_livraison' => $data['date_livraison'],
                'users_id' => $_SESSION['user_id'] ?? null
            ]);

            if (!$result) {
                throw new \Exception("Erreur lors de la création de la commande");
            }

            $orderId = $this->db->lastInsertId();
            $this->db->commit();
            return $orderId;

        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Erreur dans createOrder(): " . $e->getMessage());
            throw $e;
        }
    }

    private function normalizeStatus($status)
    {
        $statusMap = [
            '' => 'en_attente',
            'en attente' => 'en_attente',
            'en preparation' => 'en_preparation',
            'en_preparation' => 'en_preparation',
            'expediee' => 'expediee',
            'expédiée' => 'expediee',
            'en transit' => 'en_transit',
            'en_transit' => 'en_transit',
            'livree' => 'livrée',
            'livrée' => 'livrée',
            'annulee' => 'annulée',
            'annulée' => 'annulée'
        ];

        return $statusMap[strtolower($status)] ?? 'en_attente';
    }

    public function getOrders($search = '')
    {
        try {
            $query = "SELECT 
                        o.id,
                        o.product_name,
                        o.prix,
                        o.quantite,
                        o.date_livraison,
                        o.date_livraison_effective,
                        s.nom as supplier_name,
                        c.nom as category_name
                    FROM orders o
                    LEFT JOIN supplier s ON o.supplier_id = s.id
                    LEFT JOIN categories c ON o.categories_id = c.id";

            if (!empty($search)) {
                $query .= " WHERE o.product_name LIKE :search 
                           OR s.nom LIKE :search 
                           OR c.nom LIKE :search";
            }

            $query .= " ORDER BY o.date_livraison ASC";

            $stmt = $this->db->prepare($query);
            
            if (!empty($search)) {
                $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
            }

            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error in getOrders: " . $e->getMessage());
            throw new \Exception("Erreur lors de la récupération des commandes");
        }
    }

    public function getOrderById($id)
    {
        try {
            $query = "SELECT * FROM orders WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Erreur dans getOrderById(): " . $e->getMessage());
            throw $e;
        }
    }

    public function updateOrder($data)
    {
        try {
            $this->db->beginTransaction();

            $query = "UPDATE orders 
                     SET product_name = :product_name,
                         quantite = :quantite,
                         prix = :prix,
                         date_livraison = :date_livraison,
                         supplier_id = :supplier_id
                     WHERE id = :id";

            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                'id' => $data['id'],
                'product_name' => $data['product_name'],
                'quantite' => $data['quantite'],
                'prix' => $data['prix'],
                'date_livraison' => $data['date_livraison'],
                'supplier_id' => $data['supplier_id']
            ]);

            if (!$result) {
                throw new \Exception("Erreur lors de la modification de la commande");
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Erreur dans updateOrder: " . $e->getMessage());
            throw $e;
        }
    }

    public function deleteOrder($id)
    {
        try {
            $query = "DELETE FROM orders WHERE id = :id";
            $stmt = $this->db->prepare($query);
            return $stmt->execute(['id' => $id]);
        } catch (\PDOException $e) {
            error_log("Erreur dans deleteOrder(): " . $e->getMessage());
            throw $e;
        }
    }

    public function getOrderWithDetails($id)
    {
        try {
            $query = "SELECT o.*, 
                            c.nom as category_name,
                            s.nom as supplier_name,
                            s.email as supplier_email
                     FROM orders o
                     LEFT JOIN categories c ON o.categories_id = c.id
                     LEFT JOIN supplier s ON o.supplier_id = s.id
                     WHERE o.id = :id";

            $stmt = $this->db->prepare($query);
            $stmt->execute(['id' => $id]);
            
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                throw new \Exception("Commande non trouvée");
            }
            
            return $order;
        } catch (\PDOException $e) {
            error_log("Erreur dans getOrderWithDetails(): " . $e->getMessage());
            throw $e;
        }
    }

    public function updateOrderStatus($orderId)
    {
        try {
            $this->db->beginTransaction();

            $order = $this->getOrderById($orderId);
            $now = new \DateTime();
            $deliveryDate = new \DateTime($order['date_livraison']);

            // If delivery date is today or in the past
            if ($deliveryDate <= $now) {
                $query = "UPDATE orders 
                         SET date_livraison_effective = CURRENT_TIMESTAMP
                         WHERE id = :id";
                
                $stmt = $this->db->prepare($query);
                $stmt->execute(['id' => $orderId]);

                // Add to product stock if delivered
                $this->addToProductStock($order);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Erreur dans updateOrderStatus(): " . $e->getMessage());
            throw $e;
        }
    }

    private function addToProductStock($order)
    {
        // Check if product exists
        $queryCheck = "SELECT id, quantite FROM product 
                      WHERE nom = :nom 
                      AND categories_id = :categories_id 
                      AND supplier_id = :supplier_id";
        
        $stmt = $this->db->prepare($queryCheck);
        $stmt->execute([
            'nom' => $order['product_name'],
            'categories_id' => $order['categories_id'],
            'supplier_id' => $order['supplier_id']
        ]);
        
        $existingProduct = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($existingProduct) {
            // Update existing product quantity
            $queryUpdate = "UPDATE product 
                           SET quantite = quantite + :quantite 
                           WHERE id = :id";
            
            $stmt = $this->db->prepare($queryUpdate);
            $stmt->execute([
                'quantite' => $order['quantite'],
                'id' => $existingProduct['id']
            ]);
        } else {
            // Create new product
            $queryInsert = "INSERT INTO product (
                nom, 
                prix, 
                quantite, 
                categories_id, 
                supplier_id
            ) VALUES (
                :nom, 
                :prix, 
                :quantite, 
                :categories_id, 
                :supplier_id
            )";
            
            $stmt = $this->db->prepare($queryInsert);
            $stmt->execute([
                'nom' => $order['product_name'],
                'prix' => $order['prix'],
                'quantite' => $order['quantite'],
                'categories_id' => $order['categories_id'],
                'supplier_id' => $order['supplier_id']
            ]);
        }
    }

    public function getPendingOrders()
    {
        try {
            $query = "SELECT id, date_livraison FROM orders WHERE statut = 'en attente'";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Erreur dans getPendingOrders(): " . $e->getMessage());
            throw $e;
        }
    }

    public function checkAndUpdateOrdersStatus()
    {
        try {
            $this->db->beginTransaction();

            $now = new \DateTime();

            // Mise à jour des commandes en préparation
            $query = "UPDATE orders 
                     SET statut = 'en_preparation', 
                         date_preparation = CURRENT_TIMESTAMP
                     WHERE statut = 'en_attente' 
                     AND date_preparation_prevue <= :now";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['now' => $now->format('Y-m-d H:i:s')]);

            // Mise à jour des commandes expédiées
            $query = "UPDATE orders 
                     SET statut = 'expediee', 
                         date_expedition = CURRENT_TIMESTAMP
                     WHERE statut = 'en_preparation' 
                     AND date_expedition_prevue <= :now";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['now' => $now->format('Y-m-d H:i:s')]);

            // Mise à jour des commandes en transit
            $query = "UPDATE orders 
                     SET statut = 'en_transit', 
                         date_transit = CURRENT_TIMESTAMP
                     WHERE statut = 'expediee' 
                     AND date_transit_prevue <= :now";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['now' => $now->format('Y-m-d H:i:s')]);

            // Mise à jour des commandes livrées
            $query = "UPDATE orders 
                     SET statut = 'livrée', 
                         date_livraison_effective = CURRENT_TIMESTAMP
                     WHERE statut = 'en_transit' 
                     AND date_livraison <= :now";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['now' => $now->format('Y-m-d H:i:s')]);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Erreur dans checkAndUpdateOrdersStatus(): " . $e->getMessage());
            throw $e;
        }
    }
}