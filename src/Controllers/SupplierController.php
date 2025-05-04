<?php

namespace App\Controllers;

use App\Models\SupplierModel;
use Twig\Environment;
use PDO;

class SupplierController
{
    private $twig;
    private $supplierModel;

    public function __construct(Environment $twig, PDO $db)
    {
        $this->twig = $twig;
        $this->supplierModel = new SupplierModel($db);
    }

    public function listSuppliers()
    {
        try {
            $searchTerm = $_GET['search'] ?? '';
            $suppliers = $this->supplierModel->getAllSuppliers($searchTerm);

            echo $this->twig->render('gestion-fournisseurs.html.twig', [
                'suppliers' => $suppliers
            ]);
        } catch (\Exception $e) {
            error_log("Erreur dans SupplierController->listSuppliers(): " . $e->getMessage());
            echo $this->twig->render('gestion-fournisseurs.html.twig', [
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
}