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
            // Get delivered orders
            $query = "SELECT 
                        o.id,
                        o.date_livraison_effective as date,
                        o.product_name,
                        o.quantite as quantity,
                        o.prix as price,
                        s.nom as supplier_name,
                        c.nom as category_name
                     FROM orders o
                     LEFT JOIN supplier s ON o.supplier_id = s.id
                     LEFT JOIN categories c ON o.categories_id = c.id
                     WHERE o.status_id = 5  -- 5 is the ID for 'livree'
                     ORDER BY o.date_livraison_effective DESC";

            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format dates
            foreach ($entries as &$entry) {
                $date = new \DateTime($entry['date']);
                $entry['date'] = $date->format('d/m/Y H:i');
            }

            echo $this->twig->render('gestion-entree.html.twig', [
                'entries' => $entries,
                'success_message' => $_SESSION['success'] ?? null,
                'error_message' => $_SESSION['error'] ?? null
            ]);
            
            unset($_SESSION['success'], $_SESSION['error']);
        } catch (\Exception $e) {
            error_log("Erreur dans StockEntryController->index(): " . $e->getMessage());
            echo $this->twig->render('gestion-entree.html.twig', [
                'error_message' => "Une erreur est survenue lors du chargement des entrées"
            ]);
        }
    }
}