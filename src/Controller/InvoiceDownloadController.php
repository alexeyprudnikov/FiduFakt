<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Service\AuditLogger;
use App\Service\InvoiceEngine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class InvoiceDownloadController extends AbstractController
{
    public function __construct(
    ) {}

    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     */
    #[IsGranted('INVOICE_VIEW', subject: 'invoice')]
    public function __invoke(
        Invoice $invoice,
        InvoiceEngine $invoiceEngine,
        AuditLogger $auditLogger
    ): Response
    {
        $auditLogger->log(
            action: 'INVOICE_DOWNLOAD',
            userIdentifier: $this->getUser()?->getUserIdentifier() ?? 'anonymous',
            context: [
                'invoice_id' => $invoice->id->toString(),
                'invoice_number' => $invoice->invoiceNumber
            ]
        );
        $storageDir = $this->getParameter('kernel.project_dir') . '/var/storage/invoices/';
        $filePath = $storageDir . $invoice->pdfPath;
        if ($invoice->pdfPath !== null && file_exists($filePath)) {
            $pdfContent = file_get_contents($filePath);
        } else {
            // generate on-the-fly
            $html = $this->renderView('pdf/invoice.html.twig', [
                'invoice' => $invoice,
            ]);
            $xml = $invoiceEngine->generateZugferdXml($invoice);
            $pdfContent = $invoiceEngine->createZugferdPdf($html, $xml, $invoice);
        }
        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            // 'inline' statt 'attachment' öffnet das PDF oft direkt im Browser-Tab,
            // was für User meist angenehmer ist.
            'Content-Disposition' => sprintf('inline; filename="Rechnung_%s.pdf"', $invoice->invoiceNumber),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
