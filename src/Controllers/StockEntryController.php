<?php

namespace App\Controllers;

use PDO;
use Twig\Environment;
use App\Services\PermissionService;
use App\Models\TransactionModel;

class StockEntryController
{
    private $twig;
    private $permissionService;
    private $transactionModel;

    public function __construct(Environment $twig, PDO $db, PermissionService $permissionService)
    {
        $this->twig = $twig;
        $this->permissionService = $permissionService;
        $this->transactionModel = new TransactionModel($db);
    }

    public function index()
    {
        try {
            $search = $_GET['search'] ?? '';
            $dateDebut = $_GET['date_debut'] ?? '';
            $dateFin = $_GET['date_fin'] ?? '';
            $supplier = $_GET['supplier'] ?? '';
            $category = $_GET['category'] ?? '';

            // Passer les paramètres de filtrage
            $entries = $this->transactionModel->getDeliveredOrders(
                $search,
                $dateDebut,
                $dateFin,
                $supplier,
                $category
            );

            // Ajouter du débogage
            error_log("Nombre d'entrées trouvées : " . count($entries));
            if (count($entries) === 0) {
                error_log("Aucune entrée trouvée avec les paramètres : " . 
                    "search=$search, dateDebut=$dateDebut, dateFin=$dateFin, " .
                    "supplier=$supplier, category=$category");
            }

            foreach ($entries as $entry) {
                try {
                    $this->transactionModel->updateProductStock($entry);
                } catch (\Exception $e) {
                    error_log("Erreur mise à jour stock: " . $e->getMessage());
                }
            }

            $formattedEntries = array_map([$this, 'formatEntry'], $entries);

            echo $this->twig->render('gestion-entree.html.twig', [
                'entries' => $formattedEntries,
                'suppliers' => $this->transactionModel->getSuppliers(),
                'categories' => $this->transactionModel->getCategories(),
                'search' => $search,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'selected_supplier' => $supplier,
                'selected_category' => $category,
                'current_page' => 'gestion-entree'
            ]);
        } catch (\Exception $e) {
            error_log("Erreur dans index : " . $e->getMessage());
            echo $this->twig->render('gestion-entree.html.twig', [
                'error' => $e->getMessage(),
                'current_page' => 'gestion-entree'
            ]);
        }
    }

    private function formatEntry($entry)
    {
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

            $result = $this->transactionModel->deleteEntry($id);

            if ($result) {
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

    public function showHistory()
    {
        try {
            $dateStart = $_GET['dateStart'] ?? date('Y-m-d', strtotime('-1 month'));
            $dateEnd = $_GET['dateEnd'] ?? date('Y-m-d');
            $type = $_GET['type'] ?? 'all';
            $selectedCategory = $_GET['category'] ?? 'all';

            $transactions = [];
            
            // Debug des paramètres
            error_log("Paramètres de recherche : dateStart=$dateStart, dateEnd=$dateEnd, type=$type, category=$selectedCategory");
            
            if ($type === 'all' || $type === 'entree') {
                $entries = $this->transactionModel->getEntries(
                    $dateStart, 
                    $dateEnd, 
                    $selectedCategory !== 'all' ? $selectedCategory : null
                );
                error_log("Nombre d'entrées trouvées : " . count($entries));
                $transactions = array_merge($transactions, $entries);
            }
            
            if ($type === 'all' || $type === 'sortie') {
                $sorties = $this->transactionModel->getSorties(
                    $dateStart, 
                    $dateEnd, 
                    $selectedCategory !== 'all' ? $selectedCategory : null
                );
                error_log("Nombre de sorties trouvées : " . count($sorties));
                $transactions = array_merge($transactions, $sorties);
            }

            // Debug du nombre total de transactions
            error_log("Nombre total de transactions : " . count($transactions));

            // Trier les transactions par date
            usort($transactions, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            $categories = $this->transactionModel->getCategories();

            echo $this->twig->render('historique.html.twig', [
                'transactions' => $transactions,
                'categories' => $categories,
                'dateStart' => $dateStart,
                'dateEnd' => $dateEnd,
                'type' => $type,
                'selectedCategory' => $selectedCategory,
                'current_page' => 'historique',
                'debug' => true // Ajouter le mode debug pour afficher plus d'informations
            ]);

        } catch (\Exception $e) {
            error_log("Erreur complète dans showHistory : " . $e->getMessage() . "\n" . $e->getTraceAsString());
            echo $this->twig->render('historique.html.twig', [
                'error' => $e->getMessage(),
                'current_page' => 'historique'
            ]);
        }
    }
}