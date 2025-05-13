<?php

namespace App\Services;

class PermissionService {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // Tableau des permissions par rôle
    private static $permissions = [
        'admin' => [
            'F1' => true,   // Gestion des utilisateurs
            'F2' => true,   // Accès au tableau de bord
            'F3' => true,   // CRUD produits
            'F4' => true,   // Gestion des catégories
            'F5' => true,   // Entrées de stock
            'F6' => true,   // Sorties de stock
            'F7' => true,   // Consultation du stock
            'F8' => true,   // CRUD alertes
            'F9' => true,   // Fournisseurs
            'F10' => true,  // Consultation historique
            'F11' => true,  // Gestion des commandes
            'F12' => true,  // Génération de rapports
            'F13' => true,  // Recherche avancée
            'F14' => true,  // Interface responsive
            'F15' => true   // Sécurité et permissions
        ],
        'gestionnaire' => [
            'F1' => false,  // Pas d'accès à la gestion des utilisateurs
            'F2' => true,   
            'F3' => true,
            'F4' => true,
            'F5' => true,
            'F6' => true,
            'F7' => true,
            'F8' => true,
            'F9' => true,
            'F10' => true,
            'F11' => true,
            'F12' => true,
            'F13' => true,
            'F14' => true,
            'F15' => false
        ],
        'employe' => [
            'F1' => false,
            'F2' => true,
            'F3' => false,
            'F4' => false,
            'F5' => true,
            'F6' => true,
            'F7' => true,
            'F8' => false,
            'F9' => false,
            'F10' => true,
            'F11' => false,
            'F12' => false,
            'F13' => true,
            'F14' => true,
            'F15' => false
        ]
    ];

    // Vérifier les permissions
    public function checkPermission($role, $feature) {
        if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role'])) {
            header('Location: index.php?uri=login');
            exit();
        }

        if (!isset(self::$permissions[$role][$feature]) || !self::$permissions[$role][$feature]) {
            header('Location: index.php?uri=error&message=permission_denied');
            exit();
        }
        return true;
    }
}