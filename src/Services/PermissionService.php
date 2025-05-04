<?php

namespace App\Services;

class PermissionService
{
    private static $permissions = [
        'admin' => [
            'F1' => true,   // Gestion des utilisateurs
            'F2' => true,   // Accès au tableau de bord
            'F3' => true,   // CRUD produits
            'F4' => true,   // Gestion des catégories
            'F5' => true,   // Enregistrer les entrées de stock
            'F6' => true,   // Enregistrer les sorties de stock
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
            'F1' => false,  // Pas de gestion des utilisateurs
            'F2' => true,   // Accès au tableau de bord
            'F3' => true,   // CRUD produits
            'F4' => true,   // Gestion des catégories
            'F5' => true,   // Enregistrer les entrées de stock
            'F6' => true,   // Enregistrer les sorties de stock
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
        'employe' => [
            'F1' => false,  // Pas de gestion des utilisateurs
            'F2' => true,   // Accès au tableau de bord
            'F3' => false,  // Pas de CRUD produits
            'F4' => false,  // Pas de gestion des catégories
            'F5' => true,   // Enregistrer les entrées de stock
            'F6' => true,   // Enregistrer les sorties de stock
            'F7' => true,   // Consultation du stock
            'F8' => false,  // Pas de CRUD alertes
            'F9' => false,  // Pas d'accès aux fournisseurs
            'F10' => true,  // Consultation historique
            'F11' => false, // Pas de gestion des commandes
            'F12' => false, // Pas de génération de rapports
            'F13' => true,  // Recherche avancée
            'F14' => true,  // Interface responsive
            'F15' => false  // Pas de gestion des permissions
        ]
    ];

    public static function hasPermission(string $role, string $permission): bool
    {
        if (!isset(self::$permissions[$role]) || !isset(self::$permissions[$role][$permission])) {
            return false;
        }
        return self::$permissions[$role][$permission];
    }

    public static function checkPermission(string $role, string $permission): void
    {
        if (!self::hasPermission($role, $permission)) {
            header('Location: index.php?uri=error&message=permission_denied');
            exit;
        }
    }
}