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
            $searchTerm = $_GET['search'] ?? '';
            $orders = $this->orderModel->getOrders($searchTerm);
            $categories = $this->categoryModel->getAllCategories();

            echo $this->twig->render('gestion-commande.html.twig', [
                'orders' => $orders,
                'categories' => $categories,
                'success_message' => $_SESSION['success'] ?? null,
                'error_message' => $_SESSION['error'] ?? null
            ]);
            
            unset($_SESSION['success'], $_SESSION['error']);
        } catch (\Exception $e) {
            error_log("Erreur dans listOrders: " . $e->getMessage());
            echo $this->twig->render('gestion-commande.html.twig', [
                'error_message' => "Une erreur est survenue lors du chargement des commandes"
            ]);
        }
    }

    public function modifyOrder()
    {
        try {
            $id = $_GET['id'] ?? null;
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Vérifier si la commande n'est pas déjà livrée
                $order = $this->orderModel->getOrderById($id);
                $deliveryDate = new \DateTime($order['date_livraison']);
                $now = new \DateTime();
                
                if ($deliveryDate <= $now) {
                    throw new \Exception("Impossible de modifier une commande déjà livrée");
                }

                $data = [
                    'id' => $id,
                    'product_name' => $_POST['product_name'],
                    'quantite' => $_POST['quantite'],
                    'prix' => $_POST['prix'],
                    'date_livraison' => $_POST['date_livraison'],
                    'supplier_id' => $_POST['supplier_id']
                ];

                $this->orderModel->updateOrder($data);
                $_SESSION['success'] = "Commande modifiée avec succès";
                header('Location: index.php?uri=order-management');
                exit;
            }

            $order = $this->orderModel->getOrderById($id);
            $suppliers = $this->supplierModel->getAllSuppliers();

            echo $this->twig->render('modifier-commande.html.twig', [
                'order' => $order,
                'suppliers' => $suppliers
            ]);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: index.php?uri=order-management');
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