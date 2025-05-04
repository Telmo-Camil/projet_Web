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

            // Calcul des dates prévues
            $dateLivraison = new \DateTime($data['date_livraison']);
            $datePreparation = (new \DateTime($data['date_livraison']))->modify('-3 days');
            $dateExpedition = (new \DateTime($data['date_livraison']))->modify('-2 days');
            $dateTransit = (new \DateTime($data['date_livraison']))->modify('-1 day');

            $query = "INSERT INTO orders (
                product_name, prix, quantite, categories_id, 
                supplier_id, date_livraison, users_id,
                date_preparation_prevue, date_expedition_prevue, 
                date_transit_prevue
            ) VALUES (
                :product_name, :prix, :quantite, :categories_id, 
                :supplier_id, :date_livraison, :users_id,
                :date_preparation_prevue, :date_expedition_prevue, 
                :date_transit_prevue
            )";

            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                'product_name' => $data['product_name'],
                'prix' => $data['prix'],
                'quantite' => $data['quantite'],
                'categories_id' => $data['category_id'],
                'supplier_id' => $data['supplier_id'],
                'date_livraison' => $data['date_livraison'],
                'users_id' => $_SESSION['user_id'] ?? null,
                'date_preparation_prevue' => $datePreparation->format('Y-m-d H:i:s'),
                'date_expedition_prevue' => $dateExpedition->format('Y-m-d H:i:s'),
                'date_transit_prevue' => $dateTransit->format('Y-m-d H:i:s')
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

    public function getOrders($searchTerm = '', $categoryFilter = 'all', $statusFilter = 'all')
    {
        try {
            $query = "SELECT o.*, c.nom as category_name, s.nom as supplier_name 
                     FROM orders o 
                     LEFT JOIN categories c ON o.categories_id = c.id 
                     LEFT JOIN supplier s ON o.supplier_id = s.id 
                     WHERE 1=1";
            $params = [];

            if (!empty($searchTerm)) {
                $query .= " AND (o.product_name LIKE :search 
                          OR s.nom LIKE :search 
                          OR c.nom LIKE :search)";
                $params['search'] = "%$searchTerm%";
            }

            if ($categoryFilter !== 'all') {
                $query .= " AND o.categories_id = :category_id";
                $params['category_id'] = $categoryFilter;
            }

            if ($statusFilter !== 'all') {
                $query .= " AND o.statut = :status";
                $params['status'] = $statusFilter;
            }

            $query .= " ORDER BY o.date_commande DESC";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Normalisation des statuts
            foreach ($orders as &$order) {
                $order['statut'] = $this->normalizeStatus($order['statut']);
            }
            
            return $orders;
        } catch (\PDOException $e) {
            error_log("Erreur dans getOrders(): " . $e->getMessage());
            throw $e;
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
            $query = "UPDATE orders 
                     SET product_name = :product_name,
                         categories_id = :category_id,
                         supplier_id = :supplier_id,
                         prix = :prix,
                         quantite = :quantite,
                         date_livraison = :date_livraison,
                         statut = :statut
                     WHERE id = :id";

            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'id' => $data['id'],
                'product_name' => $data['product_name'],
                'category_id' => $data['category_id'],
                'supplier_id' => $data['supplier_id'],
                'prix' => $data['prix'],
                'quantite' => $data['quantite'],
                'date_livraison' => $data['date_livraison'],
                'statut' => $data['statut']
            ]);
        } catch (\PDOException $e) {
            error_log("Erreur dans updateOrder(): " . $e->getMessage());
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

    public function updateOrderStatus($orderId, $newStatus, $dateField)
    {
        try {
            $this->db->beginTransaction();

            // Met à jour le statut
            $queryStatus = "UPDATE orders 
                           SET statut = :status,
                               {$dateField} = CURRENT_TIMESTAMP
                           WHERE id = :id";
            
            $stmt = $this->db->prepare($queryStatus);
            $result = $stmt->execute([
                'status' => $newStatus,
                'id' => $orderId
            ]);

            // Si la commande est livrée, on ajoute le produit au stock
            if ($newStatus === 'livrée') {
                $order = $this->getOrderById($orderId);
                
                // Vérifie si le produit existe déjà
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
                
                $existingProduct = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingProduct) {
                    // Met à jour la quantité si le produit existe
                    $queryUpdate = "UPDATE product 
                                  SET quantite = quantite + :quantite 
                                  WHERE id = :id";
                    
                    $stmt = $this->db->prepare($queryUpdate);
                    $stmt->execute([
                        'quantite' => $order['quantite'],
                        'id' => $existingProduct['id']
                    ]);
                } else {
                    // Crée un nouveau produit si n'existe pas
                    $queryInsert = "INSERT INTO product (nom, prix, quantite, categories_id, supplier_id) 
                                  VALUES (:nom, :prix, :quantite, :categories_id, :supplier_id)";
                    
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

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Erreur dans updateOrderStatus(): " . $e->getMessage());
            throw $e;
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