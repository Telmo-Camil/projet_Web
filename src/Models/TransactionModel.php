<?php

namespace App\Models;

use PDO;

class TransactionModel
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getEntries($dateStart, $dateEnd, $categoryId = null)
    {
        try {
            $query = "SELECT 
                'entree' as type,
                o.date_livraison as date,
                o.product_name,
                c.nom as category_name,
                o.quantite as quantity,
                CONCAT('Livraison commande #', o.id) as reason,
                p.quantite as stock_after
            FROM orders o
            LEFT JOIN product p ON o.product_name = p.nom
            LEFT JOIN categories c ON o.categories_id = c.id
            WHERE o.date_livraison BETWEEN :dateStart AND :dateEnd
            AND o.stock_updated = 1";

            if ($categoryId) {
                $query .= " AND o.categories_id = :category";
            }

            $stmt = $this->db->prepare($query);
            
            $params = [
                'dateStart' => $dateStart . ' 00:00:00',
                'dateEnd' => $dateEnd . ' 23:59:59'
            ];

            if ($categoryId) {
                $params['category'] = $categoryId;
            }

            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Erreur dans getEntries : " . $e->getMessage());
            throw $e;
        }
    }

    public function getSorties($dateStart, $dateEnd, $categoryId = null)
    {
        try {
            // Debug des dates reçues
            error_log("Date start reçue : " . $dateStart);
            error_log("Date end reçue : " . $dateEnd);

            $query = "SELECT 
                'sortie' as type,
                ss.id,
                ss.date,
                p.nom as product_name,
                c.nom as category_name,
                ss.quantity,
                COALESCE(ss.reason, 'Sortie de stock') as reason,
                ss.remaining_stock as stock_after,
                p.id as product_id,
                c.id as category_id
            FROM stock_sorties ss
            JOIN product p ON ss.product_id = p.id
            JOIN categories c ON p.categories_id = c.id";

            $params = [];

            // Si les dates sont fournies, ajouter la condition BETWEEN
            if ($dateStart && $dateEnd) {
                $query .= " WHERE ss.date BETWEEN :dateStart AND :dateEnd";
                $params['dateStart'] = $dateStart . ' 00:00:00';
                $params['dateEnd'] = $dateEnd . ' 23:59:59';
            } else {
                // Si pas de dates, prendre le dernier mois par défaut
                $query .= " WHERE ss.date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)";
            }

            // Ajouter la condition de catégorie si spécifiée
            if ($categoryId) {
                $query .= " AND c.id = :categoryId";
                $params['categoryId'] = $categoryId;
            }

            // Ajouter l'ordre de tri
            $query .= " ORDER BY ss.date DESC";

            // Debug de la requête finale
            error_log("Requête SQL finale : " . $query);
            error_log("Paramètres : " . print_r($params, true));

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $sorties = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Debug des résultats
            error_log("Nombre de sorties trouvées : " . count($sorties));
            if (count($sorties) > 0) {
                error_log("Première sortie : " . print_r($sorties[0], true));
                error_log("Dernière sortie : " . print_r(end($sorties), true));
            }

            // Vérifier toutes les sorties dans la base
            $checkQuery = "SELECT COUNT(*) FROM stock_sorties";
            $totalSorties = $this->db->query($checkQuery)->fetchColumn();
            error_log("Nombre total de sorties dans la base : " . $totalSorties);

            return $sorties;

        } catch (\Exception $e) {
            error_log("Erreur dans getSorties : " . $e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e;
        }
    }

    public function getCategories()
    {
        return $this->db->query("SELECT id, nom FROM categories ORDER BY nom")
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function updateProductStock($order)
    {
        try {
            $this->db->beginTransaction();

            $existingProduct = $this->getProductByDetails(
                $order['product_name'], 
                $order['categories_id'], 
                $order['supplier_id']
            );
            
            if ($existingProduct) {
                $productId = $existingProduct['id'];
                $newStock = $existingProduct['quantite'] + $order['quantite'];
                $this->updateProductQuantity($productId, $order['quantite'], $order['prix']);
            } else {
                $productId = $this->createNewProduct($order);
                $newStock = $order['quantite'];
            }

            $this->addStockMovement($productId, $order['quantite'], $newStock, $order['id']);
            $this->markOrderAsProcessed($order['id']);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function getProductByDetails($productName, $categoryId, $supplierId)
    {
        $query = "SELECT id, quantite FROM product 
                 WHERE nom = :nom 
                 AND categories_id = :categories_id 
                 AND supplier_id = :supplier_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'nom' => $productName,
            'categories_id' => $categoryId,
            'supplier_id' => $supplierId
        ]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function updateProductQuantity($productId, $quantity, $price)
    {
        $query = "UPDATE product 
                 SET quantite = quantite + :quantite,
                     prix = :prix
                 WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'quantite' => $quantity,
            'prix' => $price,
            'id' => $productId
        ]);
    }

    private function createNewProduct($data)
    {
        $query = "INSERT INTO product (
            nom, prix, quantite, categories_id, supplier_id
        ) VALUES (
            :nom, :prix, :quantite, :categories_id, :supplier_id
        )";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'nom' => $data['product_name'],
            'prix' => $data['prix'],
            'quantite' => $data['quantite'],
            'categories_id' => $data['categories_id'],
            'supplier_id' => $data['supplier_id']
        ]);

        return $this->db->lastInsertId();
    }

    private function addStockMovement($productId, $quantity, $newStock, $orderId)
    {
        $query = "INSERT INTO stock_movements (
            product_id, type, quantity, date, reason, stock_after
        ) VALUES (
            :product_id, 'entree', :quantity, NOW(), :reason, :stock_after
        )";

        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'product_id' => $productId,
            'quantity' => $quantity,
            'reason' => 'Livraison commande #' . $orderId,
            'stock_after' => $newStock
        ]);
    }

    private function markOrderAsProcessed($orderId)
    {
        $query = "UPDATE orders SET stock_updated = 1 WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['id' => $orderId]);
    }

    public function addToProductStock($entry)
    {
        try {
            $this->db->beginTransaction();

            $existingProduct = $this->getProductByDetails(
                $entry['product_name'],
                $entry['categories_id'],
                $entry['supplier_id']
            );

            if ($existingProduct) {
                $this->updateExistingProduct($existingProduct['id'], $entry['quantity']);
            } else {
                $this->createNewProduct($entry);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error adding to stock: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateStockFromDelivery($order)
    {
        try {
            $this->db->beginTransaction();

            $existingProduct = $this->getProductByDetails(
                $order['product_name'],
                $order['categories_id'],
                $order['supplier_id']
            );

            if ($existingProduct) {
                $productId = $existingProduct['id'];
                $newStock = $existingProduct['quantite'] + $order['quantite'];
                $this->updateProductQuantityAndPrice($productId, $order['quantite'], $order['prix']);
            } else {
                $productId = $this->createNewProduct($order);
                $newStock = $order['quantite'];
            }

            $this->addStockMovement($productId, $order['quantite'], $newStock, $order['id']);
            $this->markOrderAsProcessed($order['id']);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Erreur mise à jour stock: " . $e->getMessage());
            throw $e;
        }
    }

    private function updateProductQuantityAndPrice($id, $quantity, $price)
    {
        $query = "UPDATE product 
                 SET quantite = quantite + :quantite,
                     prix = :prix
                 WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'quantite' => $quantity,
            'prix' => $price,
            'id' => $id
        ]);
    }

    private function updateExistingProduct($id, $quantity)
    {
        $query = "UPDATE product 
                 SET quantite = quantite + :quantite 
                 WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            'quantite' => $quantity,
            'id' => $id
        ]);
    }

    public function getDeliveredOrders($search = '', $dateDebut = '', $dateFin = '', $supplier = '', $category = '')
    {
        $query = "SELECT 
            o.id,
            o.date_livraison,
            o.product_name,
            o.quantite,
            o.prix,
            o.supplier_id,
            o.categories_id,
            o.stock_updated,
            s.nom as supplier_name,
            c.nom as category_name
        FROM orders o
        LEFT JOIN supplier s ON o.supplier_id = s.id
        LEFT JOIN categories c ON o.categories_id = c.id
        WHERE o.date_livraison <= CURRENT_DATE";

        $params = [];

        if ($search) {
            $query .= " AND (o.product_name LIKE :search OR s.nom LIKE :search)";
            $params['search'] = "%$search%";
        }

        if ($dateDebut) {
            $query .= " AND o.date_livraison >= :date_debut";
            $params['date_debut'] = $dateDebut;
        }

        if ($dateFin) {
            $query .= " AND o.date_livraison <= :date_fin";
            $params['date_fin'] = $dateFin;
        }

        if ($supplier) {
            $query .= " AND s.id = :supplier";
            $params['supplier'] = $supplier;
        }

        if ($category) {
            $query .= " AND c.id = :category";
            $params['category'] = $category;
        }

        $query .= " ORDER BY o.date_livraison DESC";

        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Erreur dans getDeliveredOrders : " . $e->getMessage());
            throw $e;
        }
    }

    public function deleteEntry($id)
    {
        $query = "DELETE FROM orders WHERE id = :id AND DATE(date_livraison) <= CURDATE()";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['id' => $id]);
    }

    public function getSuppliers()
    {
        return $this->db->query("SELECT id, nom FROM supplier ORDER BY nom")->fetchAll(\PDO::FETCH_ASSOC);
    }
}