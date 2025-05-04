<?php

namespace App\Controllers;

use PDO;
use Twig\Environment;
use App\Services\PermissionService;

class AuthController
{
    private $db;
    private $twig;

    public function __construct(Environment $twig, PDO $db)
    {
        $this->db = $db;
        $this->twig = $twig;
    }

    public function showLoginForm()
    {
        echo $this->twig->render('login.html.twig', [
            'error' => $_SESSION['error'] ?? null
        ]);
        unset($_SESSION['error']);
    }

    public function showRegisterForm()
    {
        // Check if user is admin
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            header('Location: index.php?uri=error&message=permission_denied');
            exit;
        }

        echo $this->twig->render('sign.html.twig', [
            'error' => $_SESSION['error'] ?? null
        ]);
        unset($_SESSION['error']);
    }

    public function login()
    {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        try {
            $query = "SELECT * FROM users WHERE email = :email LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['mot_de_passe'])) {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'nom' => $user['nom'],
                    'prenom' => $user['prenom'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ];
                header('Location: index.php?uri=/');
                exit;
            }

            $_SESSION['error'] = "Email ou mot de passe incorrect";
            header('Location: index.php?uri=login');
            exit;

        } catch (\Exception $e) {
            $_SESSION['error'] = "Une erreur est survenue";
            header('Location: index.php?uri=login');
            exit;
        }
    }

    public function register()
    {
        // Check if user is admin
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            header('Location: index.php?uri=error&message=permission_denied');
            exit;
        }

        $nom = $_POST['nom'] ?? '';
        $prenom = $_POST['prenom'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'employe';

        try {
            // Check if email already exists
            $query = "SELECT id FROM users WHERE email = :email LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['email' => $email]);
            
            if ($stmt->fetch()) {
                $_SESSION['error'] = "Cet email est déjà utilisé";
                header('Location: index.php?uri=sign');
                exit;
            }

            // Create new user
            $query = "INSERT INTO users (nom, prenom, email, mot_de_passe, role) 
                     VALUES (:nom, :prenom, :email, :password, :role)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role
            ]);

            $_SESSION['success'] = "Utilisateur créé avec succès";
            header('Location: index.php?uri=/');
            exit;

        } catch (\Exception $e) {
            $_SESSION['error'] = "Une erreur est survenue lors de la création du compte";
            header('Location: index.php?uri=sign');
            exit;
        }
    }

    public function logout()
    {
        session_destroy();
        header('Location: index.php?uri=login');
        exit;
    }
}