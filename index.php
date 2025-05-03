<?php

require_once __DIR__ . '/vendor/autoload.php';

use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use App\Controllers\ProductController;

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

// Récupération de l'URI
$uri = $_GET['uri'] ?? '/';
$action = $_GET['action'] ?? 'index';

// Instanciation des contrôleurs
$controller = new \App\Controllers\ControllerPage($twig);
$productController = new ProductController($twig, $db);

// Routage
switch ($uri) {
    case '/':
        $controller->welcomePage();
        break;
    
    case 'add-order':
        $controller->addOrder();
        break;

    case 'add-supplier':
        $controller->addSupplier();
        break;
    
    case 'add-product':
        $controller->addproduct();
        break;

    case 'dashboard':
        $controller->dashboard();
        break;

    case 'order-management':
        $controller->orderManagement();
        break;

    case 'entrance-management':
        $controller->entranceManagement();
        break;

    case 'supplier-management':
        $controller->supplierManagement();
        break;
    
    case 'product-management':
        $controller->productManagement();
        break;

    case 'login':
        $controller->login();
        break;

    case 'modify-order':
        $controller->modifyOrder();
        break;

    case 'modify-supplier':
        $controller->modifySupplier();
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
        $controller->orderTracking();
        break;
}




