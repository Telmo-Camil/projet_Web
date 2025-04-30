<?php

require_once 'vendor/autoload.php';

use Twig\Loader\FilesystemLoader;
use Twig\Environment;

// Configuration de Twig
$loader = new FilesystemLoader('templates');
$twig = new Environment($loader, [
    'cache' => false,  // Désactiver le cache en développement
    'debug' => true
]);


if (isset($_GET['uri'])) {
    $uri = $_GET['uri'];
} else {
    $uri = '/';
}


// Instancier le contrôleur avec Twig
$controller = new \App\Controllers\ControllerPage($twig);


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
        $controller->product();
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



   
    