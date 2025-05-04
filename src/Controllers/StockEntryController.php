<?php

namespace App\Controllers;

use PDO;
use Twig\Environment;

class StockEntryController
{
    private $db;
    private $twig;

    public function __construct(Environment $twig, PDO $db)
    {
        $this->twig = $twig;
        $this->db = $db;
    }

    public function index()
    {
        try {
            // First, check for delivered orders that haven't been added to stock
            $queryPending = "SELECT 
                    o.id,
                    o.date_livraison as date,
                    o.product_name,
                    o.quantite as quantity,
                    o.prix as price,
                    o.supplier_id,
                    o.categories_id,
                    COALESCE(s.nom, 'N/A') as supplier_name,
                    COALESCE(c.nom, 'N/A') as category_name,
                    o.stock_added
                FROM orders o
                LEFT JOIN supplier s ON o.supplier_id = s.id
                LEFT JOIN categories c ON o.categories_id = c.id
                WHERE DATE(o.date_livraison) <= CURDATE() 
                AND (o.stock_added = 0 OR o.stock_added IS NULL)";

            $stmt = $this->db->prepare($queryPending);
            $stmt->execute();
            $pendingEntries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Process pending entries
            foreach ($pendingEntries as $entry) {
                try {
                    $this->db->beginTransaction();
                    
                    // Add to stock
                    $this->addToProductStock($entry);
                    
                    // Mark as processed
                    $updateQuery = "UPDATE orders SET stock_added = 1 WHERE id = :id";
                    $updateStmt = $this->db->prepare($updateQuery);
                    $updateStmt->execute(['id' => $entry['id']]);
                    
                    $this->db->commit();
                } catch (\Exception $e) {
                    $this->db->rollBack();
                    error_log("Failed to process entry {$entry['id']}: " . $e->getMessage());
                }
            }

            // Now get all delivered entries for display
            $query = "SELECT 
                        o.id,
                        o.date_livraison as date,
                        o.product_name,
                        o.quantite as quantity,
                        o.prix as price,
                        o.supplier_id,
                        o.categories_id,
                        COALESCE(s.nom, 'N/A') as supplier_name,
                        COALESCE(c.nom, 'N/A') as category_name,
                        o.stock_added as stock_added
                    FROM orders o
                    LEFT JOIN supplier s ON o.supplier_id = s.id
                    LEFT JOIN categories c ON o.categories_id = c.id
                    WHERE DATE(o.date_livraison) <= CURDATE()
                    ORDER BY o.date_livraison DESC";

            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $entries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Add to stock if not already added
            foreach ($entries as &$entry) {
                if (!$entry['stock_added']) {
                    try {
                        $this->addToProductStock($entry);
                        
                        // Update order to mark stock as added
                        $updateQuery = "UPDATE orders SET stock_added = 1 WHERE id = :id";
                        $updateStmt = $this->db->prepare($updateQuery);
                        $updateStmt->execute(['id' => $entry['id']]);
                        
                        $entry['stock_added'] = true;
                    } catch (\Exception $e) {
                        error_log("Error processing entry {$entry['id']}: " . $e->getMessage());
                    }
                }

                // Format display values
                $entry['date'] = (new \DateTime($entry['date']))->format('d/m/Y');
                $entry['price'] = floatval($entry['price']);
                $entry['total'] = $entry['price'] * $entry['quantity'];
                $entry['price_formatted'] = number_format($entry['price'], 2, ',', ' ');
                $entry['total_formatted'] = number_format($entry['total'], 2, ',', ' ');
            }

            // Recherche si nécessaire
            $searchTerm = $_GET['search'] ?? '';
            if (!empty($searchTerm)) {
                $entries = array_filter($entries, function($entry) use ($searchTerm) {
                    return (!is_null($entry['product_name']) && stripos($entry['product_name'], $searchTerm) !== false) ||
                           (!is_null($entry['supplier_name']) && stripos($entry['supplier_name'], $searchTerm) !== false) ||
                           (!is_null($entry['category_name']) && stripos($entry['category_name'], $searchTerm) !== false) ||
                           (!is_null($entry['date']) && stripos($entry['date'], $searchTerm) !== false);
                });
            }

            echo $this->twig->render('gestion-entree.html.twig', [
                'entries' => array_values($entries), // Reset array keys after filtering
                'search' => $searchTerm,
                'success_message' => $_SESSION['success'] ?? null,
                'error_message' => $_SESSION['error'] ?? null
            ]);

            unset($_SESSION['success'], $_SESSION['error']);

        } catch (\Exception $e) {
            error_log("Erreur dans StockEntryController->index(): " . $e->getMessage());
            echo $this->twig->render('gestion-entree.html.twig', [
                'error_message' => "Une erreur est survenue lors du chargement des entrées",
                'entries' => []
            ]);
        }
    }

    public function deleteEntry()
    {
        try {
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                throw new \Exception("ID de l'entrée manquant");
            }

            $query = "DELETE FROM orders WHERE id = :id AND DATE(date_livraison) <= CURDATE()";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute(['id' => $id]);

            if ($result && $stmt->rowCount() > 0) {
                $_SESSION['success'] = "Entrée supprimée avec succès";
            } else {
                $_SESSION['error'] = "Impossible de supprimer cette entrée";
            }

        } catch (\Exception $e) {
            error_log("Erreur dans deleteEntry: " . $e->getMessage());
            $_SESSION['error'] = "Une erreur est survenue lors de la suppression";
        }

        header('Location: index.php?uri=gestion-entree');
        exit;
    }

    private function addToProductStock($entry)
    {
        try {
            $this->db->beginTransaction();

            // Check if product exists
            $query = "SELECT id, quantite FROM product 
                     WHERE nom = :nom 
                     AND categories_id = :categories_id 
                     AND supplier_id = :supplier_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'nom' => $entry['product_name'],
                'categories_id' => $entry['categories_id'],
                'supplier_id' => $entry['supplier_id']
            ]);
            
            $existingProduct = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existingProduct) {
                // Update existing product quantity
                $query = "UPDATE product 
                         SET quantite = quantite + :quantite 
                         WHERE id = :id";
                
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    'quantite' => $entry['quantity'],
                    'id' => $existingProduct['id']
                ]);
            } else {
                // Create new product
                $query = "INSERT INTO product (
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
                
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    'nom' => $entry['product_name'],
                    'prix' => $entry['price'],
                    'quantite' => $entry['quantity'],
                    'categories_id' => $entry['categories_id'],
                    'supplier_id' => $entry['supplier_id']
                ]);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error adding to stock: " . $e->getMessage());
            throw $e;
        }
    }
}