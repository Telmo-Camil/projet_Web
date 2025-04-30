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
}