<?php
session_start();

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

$twig->addGlobal('app', [
    'session' => [
        'get' => function($key) {
            return $_SESSION[$key] ?? null;
        }
    ]
]);

$twig->addGlobal('session', [
    'user' => $_SESSION['user'] ?? null
]);

// Récupération de l'URI
$uri = $_GET['uri'] ?? '/';
$action = $_GET['action'] ?? 'index';

// Instanciation des contrôleurs
$controller = new \App\Controllers\ControllerPage($twig, $db);
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
        $dashboardController = new App\Controllers\DashboardController($twig, $db);
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
        $authController = new App\Controllers\AuthController($twig, $db);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $authController->login();
        } else {
            $authController->showLoginForm();
        }
        break;

    case 'sign':
        $authController = new App\Controllers\AuthController($twig, $db);
        $authController->showRegisterForm();
        break;

    case 'register':
        $authController = new App\Controllers\AuthController($twig, $db);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $authController->register();
        } else {
            $authController->showRegisterForm();
        }
        break;

    case 'logout':
        $authController = new App\Controllers\AuthController($twig, $db);
        $authController->logout();
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
        $stockEntryController = new App\Controllers\StockEntryController($twig, $db);
        $stockEntryController->index();
        break;

    case 'delete-entry':
        $stockEntryController = new App\Controllers\StockEntryController($twig, $db);
        $stockEntryController->deleteEntry();
        break;

    case 'get-chart-data':
        $dashboardController = new App\Controllers\DashboardController($twig, $db);
        $dashboardController->getChartData();
        break;

    case 'gestion-sortie':
        $sortiController = new App\Controllers\StockSortieController($twig, $db);
        $sortiController->index();
        break;

    case 'add-sortie':
        $sortiController = new App\Controllers\StockSortieController($twig, $db);
        $sortiController->addSortie();
        break;

    case 'historique':
        $historiqueController = new App\Controllers\HistoriqueController($twig, $db);
        $historiqueController->index();
        break;

    case 'rapport':
        $reportController = new App\Controllers\ReportGeneratorController($twig, $db);
        $reportController->index();
        break;

    case 'get-chart-data':
        $rapportController = new App\Controllers\RapportController($twig, $db);
        $rapportController->getChartData();
        break;

    case 'preview-report':
        $rapportController = new App\Controllers\RapportController($twig, $db);
        $rapportController->previewReport();
        break;

    case 'generate-report':
        $reportGenerator = new App\Controllers\ReportGeneratorController($twig, $db);
        $reportGenerator->generateReport();
        break;

    case 'download-report':
        $reportGenerator = new App\Controllers\ReportGeneratorController($twig, $db);
        $reportGenerator->downloadReport($_GET['id']);
        break;

    case 'delete-report':
        $reportGenerator = new App\Controllers\ReportGeneratorController($twig, $db);
        $reportGenerator->deleteReport($_GET['id']);
        break;
}




