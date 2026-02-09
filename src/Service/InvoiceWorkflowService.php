<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

readonly class InvoiceWorkflowService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InvoiceMailService $invoiceMailService,
    ) {}

    /**
     * @throws TransportExceptionInterface
     */
    public function issue(Invoice $invoice): void
    {
        $user = $invoice->user;
        if ($user === null) {
            throw new LogicException('Rechnung hat keinen Eigentumer.');
        }
        if (!in_array ($invoice->status, [InvoiceStatus::DRAFT, InvoiceStatus::DISPATCHABLE], true)) {
            throw new LogicException('Nur Entwürfe können ausgestellt werden.');
        }
        $dispatchable = $invoice->status === InvoiceStatus::DISPATCHABLE;

        $invoice->status = InvoiceStatus::ISSUED;
        $invoice->user?->incrementInvoiceCount();
        $this->entityManager->flush();

        // Versenden
        if ($dispatchable) {
            $this->invoiceMailService->sendInvoice($invoice);
            $invoice->status = InvoiceStatus::SENT;
            $this->entityManager->flush();
        }
    }

    /**
     * Markiert die Rechnung als bezahlt und löst Folgeprozesse aus.
     */
    public function markAsPaid(Invoice $invoice): void
    {
        if ($invoice->status === InvoiceStatus::CANCELLED) {
            throw new LogicException('Stornierte Rechnungen können nicht als bezahlt markiert werden.');
        }

        $invoice->status = InvoiceStatus::PAID;
        $this->entityManager->flush();
    }
}
