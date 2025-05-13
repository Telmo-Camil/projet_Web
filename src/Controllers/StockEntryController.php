<?php

namespace App\Controllers;

use PDO;
use Twig\Environment;
use App\Services\PermissionService;

class StockEntryController
{
    private $db;
    private $twig;
    private $permissionService;

    public function __construct(Environment $twig, PDO $db, PermissionService $permissionService)
    {
        $this->twig = $twig;
        $this->db = $db;
        $this->permissionService = $permissionService;
    }

    public function index()
    {
        try {
            // Récupérer les paramètres de filtrage
            $search = $_GET['search'] ?? '';
            $dateDebut = $_GET['date_debut'] ?? '';
            $dateFin = $_GET['date_fin'] ?? '';
            $supplier = $_GET['supplier'] ?? '';
            $category = $_GET['category'] ?? '';

            // Récupérer toutes les commandes livrées (date de livraison <= aujourd'hui)
            $query = "SELECT 
                    o.id,
                    o.date_livraison,
                    o.product_name,
                    o.quantite,
                    o.prix,
                    s.nom as supplier_name,
                    c.nom as category_name
                FROM orders o
                LEFT JOIN supplier s ON o.supplier_id = s.id
                LEFT JOIN categories c ON o.categories_id = c.id
                WHERE o.date_livraison <= CURRENT_DATE";

            // Ajouter les conditions de filtrage
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

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $entries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Formater les données
            $formattedEntries = array_map(function($entry) {
                return [
                    'id' => $entry['id'],
                    'date' => (new \DateTime($entry['date_livraison']))->format('d/m/Y'),
                    'product_name' => $entry['product_name'],
                    'category_name' => $entry['category_name'],
                    'quantity' => $entry['quantite'],
                    'price_formatted' => number_format($entry['prix'], 2, ',', ' '),
                    'supplier_name' => $entry['supplier_name'],
                    'total_formatted' => number_format($entry['prix'] * $entry['quantite'], 2, ',', ' ')
                ];
            }, $entries);

            // Récupérer la liste des fournisseurs et catégories pour les filtres
            $suppliers = $this->db->query("SELECT id, nom FROM supplier ORDER BY nom")->fetchAll(\PDO::FETCH_ASSOC);
            $categories = $this->db->query("SELECT id, nom FROM categories ORDER BY nom")->fetchAll(\PDO::FETCH_ASSOC);

            echo $this->twig->render('gestion-entree.html.twig', [
                'entries' => $formattedEntries,
                'suppliers' => $suppliers,
                'categories' => $categories,
                'search' => $search,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'selected_supplier' => $supplier,
                'selected_category' => $category,
                'current_page' => 'gestion-entree'
            ]);

        } catch (\Exception $e) {
            echo $this->twig->render('gestion-entree.html.twig', [
                'error' => $e->getMessage(),
                'current_page' => 'gestion-entree'
            ]);
        }
    }

    public function deleteEntry()
    {
        try {
            // Vérifier les permissions
            $userRole = $_SESSION['user_role'] ?? 'employe';
            $this->permissionService->checkPermission($userRole, 'gestion_entrees_stock');

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