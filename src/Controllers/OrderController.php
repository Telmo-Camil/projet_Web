<?php

namespace App\Controllers;

use App\Models\OrderModel;
use App\Models\CategoryModel;
use App\Models\SupplierModel;
use Twig\Environment;
use PDO;

class OrderController
{
    private $twig;
    private $orderModel;
    private $categoryModel;
    private $supplierModel;
    private $db; // Add this line

    public function __construct(Environment $twig, PDO $db)
    {
        $this->twig = $twig;
        $this->db = $db;
        $this->orderModel = new OrderModel($db);
        $this->categoryModel = new CategoryModel($db);
        $this->supplierModel = new SupplierModel($db);
    }

    public function add()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $productName = $_POST['product_name'] ?? '';
                $categoryId = $_POST['category_id'] ?? '';
                $supplierId = $_POST['supplier_id'] ?? '';
                $prix = $_POST['prix'] ?? '';
                $quantite = $_POST['quantite'] ?? '';
                $dateLivraison = $_POST['date_livraison'] ?? '';

                if (empty($productName) || empty($categoryId) || empty($supplierId) || 
                    empty($prix) || empty($quantite) || empty($dateLivraison)) {
                    throw new \Exception("Tous les champs sont obligatoires");
                }

                $orderId = $this->orderModel->createOrder([
                    'product_name' => $productName,
                    'category_id' => $categoryId,
                    'supplier_id' => $supplierId,
                    'prix' => $prix,
                    'quantite' => $quantite,
                    'date_livraison' => $dateLivraison,
                    'status' => 'en_attente'
                ]);

                header('Location: index.php?uri=order-management&success=1');
                exit;
            }

            $categories = $this->categoryModel->getAllCategories();
            $suppliers = $this->supplierModel->getAllSuppliers();

            echo $this->twig->render('ajouter-commande.html.twig', [
                'categories' => $categories,
                'suppliers' => $suppliers
            ]);
        } catch (\Exception $e) {
            error_log("Erreur dans OrderController->add(): " . $e->getMessage());
            echo $this->twig->render('ajouter-commande.html.twig', [
                'error_message' => $e->getMessage(),
                'categories' => $categories ?? [],
                'suppliers' => $suppliers ?? []
            ]);
        }
    }

    public function listOrders()
    {
        try {
            // Mise à jour automatique des statuts
            $this->orderModel->checkAndUpdateOrdersStatus();

            $searchTerm = $_GET['search'] ?? '';
            $categoryFilter = $_GET['category'] ?? 'all';
            $statusFilter = $_GET['status'] ?? 'all';

            $orders = $this->orderModel->getOrders($searchTerm, $categoryFilter, $statusFilter);
            $categories = $this->categoryModel->getAllCategories();

            echo $this->twig->render('gestion-commande.html.twig', [
                'orders' => $orders,
                'categories' => $categories,
                'currentDate' => date('Y-m-d')
            ]);
        } catch (\Exception $e) {
            error_log("Erreur dans OrderController->listOrders(): " . $e->getMessage());
            echo $this->twig->render('gestion-commande.html.twig', [
                'error_message' => "Une erreur est survenue lors du chargement des commandes"
            ]);
        }
    }

    public function modifyOrder()
    {
        try {
            $orderId = $_GET['id'] ?? null;
            if (!$orderId) {
                throw new \Exception("ID de commande manquant");
            }

            // Vérifier si la commande existe et est en attente
            $order = $this->orderModel->getOrderById($orderId);
            if (!$order) {
                throw new \Exception("Commande non trouvée");
            }

            if ($order['statut'] !== 'en attente') {
                throw new \Exception("Seules les commandes en attente peuvent être modifiées");
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $data = [
                    'id' => $orderId,
                    'product_name' => $_POST['product_name'] ?? '',
                    'category_id' => $_POST['category_id'] ?? '',
                    'supplier_id' => $_POST['supplier_id'] ?? '',
                    'prix' => $_POST['prix'] ?? '',
                    'quantite' => $_POST['quantite'] ?? '',
                    'date_livraison' => $_POST['date_livraison'] ?? '',
                    'statut' => 'en attente' // Force le statut à rester en attente
                ];

                if (empty($data['product_name']) || empty($data['category_id']) || 
                    empty($data['supplier_id']) || empty($data['prix']) || 
                    empty($data['quantite']) || empty($data['date_livraison'])) {
                    throw new \Exception("Tous les champs sont obligatoires");
                }

                $this->orderModel->updateOrder($data);
                header('Location: index.php?uri=order-management&success=1');
                exit;
            }

            $categories = $this->categoryModel->getAllCategories();
            $suppliers = $this->supplierModel->getAllSuppliers();

            echo $this->twig->render('modifier-commande.html.twig', [
                'order' => $order,
                'categories' => $categories,
                'suppliers' => $suppliers
            ]);
        } catch (\Exception $e) {
            error_log("Erreur dans OrderController->modifyOrder(): " . $e->getMessage());
            header('Location: index.php?uri=order-management&error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    public function deleteOrder()
    {
        try {
            $orderId = $_GET['id'] ?? null;
            if (!$orderId) {
                throw new \Exception("ID de commande manquant");
            }

            $this->orderModel->deleteOrder($orderId);
            header('Location: index.php?uri=order-management&success=2');
            exit;
        } catch (\Exception $e) {
            error_log("Erreur dans OrderController->deleteOrder(): " . $e->getMessage());
            header('Location: index.php?uri=order-management&error=1');
            exit;
        }
    }

    public function trackOrder()
    {
        try {
            $orderId = $_GET['id'] ?? null;
            if (!$orderId) {
                throw new \Exception("ID de commande manquant");
            }

            $order = $this->orderModel->getOrderWithDetails($orderId);
            if (!$order) {
                throw new \Exception("Commande non trouvée");
            }

            echo $this->twig->render('suivi-commande.html.twig', [
                'order' => $order
            ]);
        } catch (\Exception $e) {
            error_log("Erreur dans OrderController->trackOrder(): " . $e->getMessage());
            header('Location: index.php?uri=order-management&error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    public function updateOrderStatus()
    {
        try {
            $orderId = $_GET['id'] ?? null;
            $newStatus = $_POST['new_status'] ?? null;

            if (!$orderId || !$newStatus) {
                throw new \Exception("Données manquantes");
            }

            $dateField = 'date_' . str_replace('_', '', $newStatus);
            $this->orderModel->updateOrderStatus($orderId, $newStatus, $dateField);

            header('Location: index.php?uri=tracking-order&id=' . $orderId . '&success=1');
            exit;
        } catch (\Exception $e) {
            error_log("Erreur dans OrderController->updateOrderStatus(): " . $e->getMessage());
            header('Location: index.php?uri=tracking-order&id=' . $orderId . '&error=' . urlencode($e->getMessage()));
            exit;
        }
    }
}