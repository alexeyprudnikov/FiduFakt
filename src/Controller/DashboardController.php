<?php

namespace App\Controller;

use App\Entity\ApiKey;
use App\Entity\Invoice;
use App\Entity\User;
use App\Repository\InvoiceRepository;
use App\Repository\UserRepository;
use App\Service\InvoiceDownloadService;
use App\State\InvoiceDownloadProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[Route('/app', name: 'app_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
    )
    {

    }
    #[Route('/dashboard', name: 'dashboard')]
    public function index(
        InvoiceRepository $invoiceRepository,
    ): Response
    {
        $user = $this->userRepository->findOneBy(['email' => $this->getUser()->getUserIdentifier()]);
        if (!($user instanceof User)) {
            throw $this->createAccessDeniedException();
        }
        return $this->render('dashboard/index.html.twig', [
            'latestInvoices' => $invoiceRepository->findLatestByUser($user),
            'invoiceStats' => $invoiceRepository->getStatsByUser($user),
        ]);
    }

    #[Route('/invoices', name: 'invoices')]
    public function invoices(): Response
    {
        $user = $this->userRepository->findOneBy(['email' => $this->getUser()->getUserIdentifier()]);
        return $this->render('dashboard/invoices.html.twig', ['invoices' => $user->invoices]);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    #[Route('/invoice/{id}/download', name: 'invoice_download')]
    public function downloadInvoices(
        Invoice $invoice,
        InvoiceDownloadService  $invoiceDownloadService
    ): Response
    {
        $pdfContent = $invoiceDownloadService->getPdfContent($invoice);
        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            // 'inline' statt 'attachment' öffnet das PDF oft direkt im Browser-Tab,
            // was für User meist angenehmer ist.
            'Content-Disposition' => sprintf('inline; filename="Rechnung_%s.pdf"', $invoice->invoiceNumber),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
