<?php

namespace App\Controllers;
use App\Models;
use Twig\Environment;

class ControllerPage
{
    private $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function welcomePage()
    {
        echo $this->twig->render('index.html.twig');
    }

    public function addOrder()
    {
        echo $this->twig->render('ajouter-commande.html.twig');
    }

    public function addSupplier()
    {
        echo $this->twig->render('ajouter-fournisseur.html.twig');
    }

    public function addProduct()
    {
        echo $this->twig->render('ajouter-produit.html.twig');
    }

    public function dashboard()
    {
        echo $this->twig->render('dashboard.html.twig');
    }

    public function orderManagement()
    {
        echo $this->twig->render('gestion-commande.html.twig');
    }

    public function entranceManagement()
    {
        echo $this->twig->render('gestion-entrée.html.twig');
    }

    public function supplierManagement()
    {
        echo $this->twig->render('gestion-fournisseurs.html.twig');
    }

    public function productManagement()
    {
        echo $this->twig->render('gestion-produit.html.twig');
    }
    
    public function login()
    {
        echo $this->twig->render('login.html.twig');
    }

    public function modifyOrder()
    {
        echo $this->twig->render('modifier-commande.html.twig');
    }

    public function modifySupplier()
    {
        echo $this->twig->render('modifier-fournisseur.html.twig');
    }

    public function modifyProduct()
    {
        echo $this->twig->render('modifier-produit.html.twig');
    }

    public function product()
    {
        echo $this->twig->render('produit.html.twig');
    }
    
    public function settings()
    {
        echo $this->twig->render('settings.html.twig');
    }

    public function sign()
    {
        echo $this->twig->render('sign-up.html.twig');
    }

    public function orderTracking()
    {
        echo $this->twig->render('suivi-commande.html.twig');
    }

}