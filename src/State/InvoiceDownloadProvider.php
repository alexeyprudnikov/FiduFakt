<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Service\InvoiceDownloadService;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

readonly class InvoiceDownloadProvider implements ProviderInterface
{
    public function __construct(
        private ProviderInterface $itemProvider,
        private InvoiceDownloadService $invoiceDownloadService,
    ) {}

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $invoice = $this->itemProvider->provide($operation, $uriVariables, $context);
        $pdfContent = $this->invoiceDownloadService->getPdfContent($invoice);
        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            // 'inline' statt 'attachment' öffnet das PDF oft direkt im Browser-Tab,
            // was für User meist angenehmer ist.
            'Content-Disposition' => sprintf('inline; filename="Rechnung_%s.pdf"', $invoice->invoiceNumber),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
