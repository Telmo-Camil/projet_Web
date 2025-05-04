<?php

namespace App\Controllers;

use PDO;
use Twig\Environment;

class StockSortieController
{
    private $db;
    private $twig;

    public function __construct(Environment $twig, PDO $db)
    {
        $this->db = $db;
        $this->twig = $twig;
    }

    public function index()
    {
        try {
            // Récupérer la liste des produits
            $queryProducts = "SELECT id, nom, quantite FROM product WHERE quantite > 0";
            $stmt = $this->db->query($queryProducts);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Récupérer l'historique des sorties
            $querySorties = "SELECT 
                                s.date,
                                p.nom as product_name,
                                s.quantity,
                                s.reason,
                                s.remaining_stock
                            FROM stock_sorties s
                            JOIN product p ON s.product_id = p.id
                            ORDER BY s.date DESC";
            $stmt = $this->db->query($querySorties);
            $sorties = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo $this->twig->render('gestion-sortie.html.twig', [
                'products' => $products,
                'sorties' => $sorties
            ]);
        } catch (\Exception $e) {
            echo $this->twig->render('gestion-sortie.html.twig', [
                'error_message' => "Une erreur est survenue lors du chargement de la page"
            ]);
        }
    }

    public function addSortie()
    {
        try {
            $productId = $_POST['product_id'] ?? null;
            $quantity = $_POST['quantity'] ?? null;
            $reason = $_POST['reason'] ?? null;

            if (!$productId || !$quantity || !$reason) {
                throw new \Exception("Tous les champs sont obligatoires");
            }

            $this->db->beginTransaction();

            // Vérifier le stock disponible
            $query = "SELECT quantite FROM product WHERE id = :id FOR UPDATE";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['id' => $productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product['quantite'] < $quantity) {
                throw new \Exception("Stock insuffisant");
            }

            // Mettre à jour le stock
            $query = "UPDATE product 
                     SET quantite = quantite - :quantity 
                     WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'quantity' => $quantity,
                'id' => $productId
            ]);

            // Enregistrer la sortie
            $query = "INSERT INTO stock_sorties (
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
            )";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'product_id' => $productId,
                'quantity' => $quantity,
                'reason' => $reason,
                'remaining_stock' => $product['quantite'] - $quantity
            ]);

            $this->db->commit();
            $_SESSION['success'] = "Sortie enregistrée avec succès";

        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: index.php?uri=gestion-sortie');
        exit;
    }
}