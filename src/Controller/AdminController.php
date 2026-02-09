<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin_')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'dashboard')]
    public function index(UserRepository $userRepo): Response
    {
        $users = $userRepo->findAll();

        $totalInvoices = 0;
        $activeSubscriptions = 0;
        foreach ($users as $user) {
            $totalInvoices += $user->invoiceCount;
            if ($user->subscription) {
                $activeSubscriptions++;
            }
        }

        return $this->render('admin/index.html.twig', [
            'users' => $users,
            'stats' => [
                'total_users' => count($users),
                'total_invoices' => $totalInvoices,
                'active_subs' => $activeSubscriptions,
            ]
        ]);
    }
}
