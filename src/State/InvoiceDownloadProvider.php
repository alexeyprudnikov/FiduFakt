<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Service\AuditLogger;
use App\Service\InvoiceEngine;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

readonly class InvoiceDownloadProvider implements ProviderInterface
{
    public function __construct(
        private string $projectDir,
        private ProviderInterface $itemProvider,
        private Security $security,
        private InvoiceEngine $invoiceEngine,
        private AuditLogger $auditLogger,
        private Environment $twig,
    ) {}

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $securityUser = $this->security->getUser();
        $invoice = $this->itemProvider->provide($operation, $uriVariables, $context);
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
        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            // 'inline' statt 'attachment' öffnet das PDF oft direkt im Browser-Tab,
            // was für User meist angenehmer ist.
            'Content-Disposition' => sprintf('inline; filename="Rechnung_%s.pdf"', $invoice->invoiceNumber),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
