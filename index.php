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
    }