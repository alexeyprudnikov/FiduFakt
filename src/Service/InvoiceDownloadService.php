<?php

namespace App\Service;

use App\Entity\Invoice;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

readonly class InvoiceDownloadService
{
    public function __construct(
        private string $projectDir,
        private Security $security,
        private AuditLogger $auditLogger,
        private Environment $twig,
        private InvoiceEngine $invoiceEngine
    )
    {

    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function getPdfContent(Invoice $invoice): string
    {
        $securityUser = $this->security->getUser();
        if ($securityUser->getUserIdentifier() !== $invoice->user?->getUserIdentifier()) {
            throw new AccessDeniedHttpException();
        }
        $this->auditLogger->log(
            action: 'INVOICE_DOWNLOAD',
            userIdentifier: $securityUser?->getUserIdentifier() ?? 'anonymous',
            context: [
                'invoice_id' => $invoice->id->toString(),
                'invoice_number' => $invoice->invoiceNumber
            ]
        );

        $storageDir = $this->projectDir . '/var/storage/invoices/';
        $filePath = $storageDir . $invoice->pdfPath;
        if ($invoice->pdfPath !== null && file_exists($filePath)) {
            $pdfContent = file_get_contents($filePath);
        } else {
            // generate on-the-fly
            $html = $this->twig->render('pdf/invoice.html.twig', [
                'invoice' => $invoice,
            ]);
            $xml = $this->invoiceEngine->generateZugferdXml($invoice);
            $pdfContent = $this->invoiceEngine->createZugferdPdf($html, $xml, $invoice);
        }
        return $pdfContent;
    }
}
