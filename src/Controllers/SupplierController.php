<?php

namespace App\Controllers;

use App\Models\SupplierModel;
use Twig\Environment;
use PDO;

class SupplierController
{
    private $twig;
    private $supplierModel;
    private $db;

    public function __construct(Environment $twig, PDO $db)
    {
        $this->twig = $twig;
        $this->db = $db;
        $this->supplierModel = new SupplierModel($db);
    }

    public function listSuppliers()
    {
        try {
            $searchTerm = $_GET['search'] ?? '';
            $suppliers = $this->supplierModel->getAllSuppliers($searchTerm);

            echo $this->twig->render('gestion-fournisseur.html.twig', [
                'suppliers' => $suppliers,
                'success_message' => $_SESSION['success'] ?? null,
                'error_message' => $_SESSION['error'] ?? null
            ]);
            unset($_SESSION['success'], $_SESSION['error']);
        } catch (\Exception $e) {
            error_log("Erreur dans SupplierController->listSuppliers(): " . $e->getMessage());
            echo $this->twig->render('gestion-fournisseur.html.twig', [
                'error_message' => "Une erreur est survenue lors du chargement des fournisseurs"
            ]);
        }
    }

    public function add()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $nom = $_POST['nom'] ?? '';
                $email = $_POST['email'] ?? '';
                $telephone = $_POST['telephone'] ?? '';
                $adresse = $_POST['adresse'] ?? '';

                if (empty($nom) || empty($email) || empty($telephone) || empty($adresse)) {
                    throw new \Exception("Tous les champs sont obligatoires");
                }

                $this->supplierModel->addSupplier([
                    'nom' => $nom,
                    'email' => $email,
                    'telephone' => $telephone,
                    'adresse' => $adresse
                ]);

                header('Location: index.php?uri=supplier-management&success=1');
                exit;
            }

            echo $this->twig->render('ajouter-fournisseur.html.twig');
        } catch (\Exception $e) {
            error_log("Erreur dans SupplierController->add(): " . $e->getMessage());
            echo $this->twig->render('ajouter-fournisseur.html.twig', [
                'error_message' => $e->getMessage()
            ]);
        }
    }

    public function delete()
    {
        try {
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new \Exception("ID fournisseur manquant");
            }

            $this->db->beginTransaction();
            
            if ($this->supplierModel->deleteSupplier($id)) {
                $this->db->commit();
                $_SESSION['success'] = "Fournisseur supprimé avec succès";
            } else {
                throw new \Exception("Erreur lors de la suppression");
            }

            header('Location: index.php?uri=supplier-management');
            exit;
        } catch (\Exception $e) {
            $this->db->rollBack();
            $_SESSION['error'] = $e->getMessage();
            header('Location: index.php?uri=supplier-management');
            exit;
        }
    }

    public function modify()
    {
        try {
            $id = $_GET['id'] ?? null;

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->supplierModel->updateSupplier([
                    'id' => $_POST['id'],
                    'nom' => $_POST['nom'],
                    'email' => $_POST['email'],
                    'telephone' => $_POST['telephone'],
                    'adresse' => $_POST['adresse']
                ]);

                $_SESSION['success'] = "Fournisseur modifié avec succès";
                header('Location: index.php?uri=supplier-management');
                exit;
            }

            $supplier = $this->supplierModel->getSupplierById($id);
            if (!$supplier) {
                throw new \Exception("Fournisseur non trouvé");
            }

            echo $this->twig->render('modifier-fournisseur.html.twig', [
                'supplier' => $supplier
            ]);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: index.php?uri=supplier-management');
            exit;
        }
    }

    public function index()
    {
        try {
            $suppliers = $this->supplierModel->getAllSuppliers();
            echo $this->twig->render('gestion-fournisseur.html.twig', [
                'suppliers' => $suppliers,
                'success_message' => $_SESSION['success'] ?? null,
                'error_message' => $_SESSION['error'] ?? null
            ]);
            unset($_SESSION['success'], $_SESSION['error']);
        } catch (\Exception $e) {
            error_log("Erreur dans SupplierController->index(): " . $e->getMessage());
            echo $this->twig->render('gestion-fournisseur.html.twig', [
                'error_message' => "Une erreur est survenue lors du chargement des fournisseurs"
            ]);
        }
    }
}