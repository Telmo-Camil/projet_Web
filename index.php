<?php

require_once __DIR__ . '/vendor/autoload.php';

use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use App\Controllers\ProductController;
use App\Controllers\CategoryController;
use App\Controllers\OrderController;
use App\Controllers\SupplierController; // Add this line
use App\Controllers\DashboardController;

// Configuration de la base de données
$dbConfig = require_once __DIR__ . '/config/database.php';

// Connexion à la base de données
try {
    $db = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8",
        $dbConfig['username'],
        $dbConfig['password']
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erreur de connexion : ' . $e->getMessage());
}

// Configuration de Twig
$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig = new Environment($loader, [
    'cache' => false,
    'debug' => true
]);
$twig->addExtension(new \Twig\Extension\DebugExtension());

// Récupération de l'URI
$uri = $_GET['uri'] ?? '/';
$action = $_GET['action'] ?? 'index';

// Instanciation des contrôleurs
$controller = new \App\Controllers\ControllerPage($twig);
$productController = new ProductController($twig, $db);
$categoryController = new \App\Controllers\CategoryController($twig, $db);

// Routage
switch ($uri) {
    case '/':
        $controller->welcomePage();
        break;
    
    case 'add-order':
        $orderController = new OrderController($twig, $db);
        $orderController->add();
        break;

    case 'add-supplier':
        $supplierController = new SupplierController($twig, $db);
        $supplierController->add();
        break;
    
    case 'add-product':
        $controller->addproduct();
        break;

    case 'dashboard':
        $dashboardController = new DashboardController($twig, $db);
        $dashboardController->index();
        break;

    case 'order-management':
        $orderController = new OrderController($twig, $db);
        $orderController->listOrders();
        break;

    case 'entrance-management':
        $controller->entranceManagement();
        break;

    case 'supplier-management':
        $supplierController = new SupplierController($twig, $db);
        $supplierController->index();
        break;
    
    case 'product-management':
        $controller->productManagement();
        break;

    case 'login':
        $controller->login();
        break;

    case 'modify-order':
        $orderController = new OrderController($twig, $db);
        $orderController->modifyOrder();
        break;

    case 'delete-order':
        $orderController = new OrderController($twig, $db);
        $orderController->deleteOrder();
        break;

    case 'modify-supplier':
        $supplierController = new SupplierController($twig, $db);
        $supplierController->modify();
        break;

    case 'delete-supplier':
        $supplierController = new SupplierController($twig, $db);
        $supplierController->delete();
        break;

    case 'modify-product':
        $controller->modifyProduct();
        break;
    
    case 'product':
        $productController->index();
        break;

    case 'settings':
        $controller->settings();
        break;

    case 'sign':
        $controller->sign();
        break;

    case 'tracking-order':
        $orderController = new OrderController($twig, $db);
        $orderController->trackOrder();
        break;

    case 'update-order-status':
        $orderController = new OrderController($twig, $db);
        $orderController->updateOrderStatus();
        break;

    case 'tracking-order':
        $controller->orderTracking();
        break;

    case 'gestion-produit':
        $categoryController->index();
        break;

    case 'add-category':
        $categoryController->add();
        break;

    case 'gestion-entree':
        $stockEntryController = new StockEntryController($twig, $db);
        $stockEntryController->index();
        break;
}




