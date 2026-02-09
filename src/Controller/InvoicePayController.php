<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Service\InvoiceWorkflowService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class InvoicePayController extends AbstractController
{
    public function __construct(
        private readonly InvoiceWorkflowService $workflowService,
    ) {}

    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function __invoke(Invoice $invoice): JsonResponse
    {
        $this->workflowService->markAsPaid($invoice);
        return new JsonResponse("$invoice->invoiceNumber has been marked as paid");
    }
}
