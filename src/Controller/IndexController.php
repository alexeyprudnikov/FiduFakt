<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class IndexController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }
        return $this->render('index.html.twig');
    }
}
