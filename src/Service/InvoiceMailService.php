<?php

namespace App\Service;

use App\Entity\Invoice;
use RuntimeException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

readonly class InvoiceMailService
{
    public function __construct(
        private string          $projectDir,
        private MailerInterface $mailer,
        private string          $senderEmail = 'billing@fidufakt.de'
    )
    {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendInvoice(Invoice $invoice): void
    {
        $pdfPath = $this->projectDir . '/var/storage/invoices/' . $invoice->pdfPath;
        if ($invoice->pdfPath === null || !file_exists($pdfPath)) {
            throw new RuntimeException("PDF-Datei für Rechnung {$invoice->invoiceNumber} nicht gefunden.");
        }
        $email = new TemplatedEmail()
            ->from(new Address($this->senderEmail, 'FiduFakt Billing'))
            ->to($invoice->rawPayload['customer']['email']) // Zugriff via rawPayload
            ->subject("Ihre Rechnung {$invoice->invoiceNumber}")
            ->htmlTemplate('email/invoice.html.twig')
            ->context([
                'invoice' => $invoice,
                'customerName' => $invoice->rawPayload['customer']['name']
            ])
            ->attachFromPath($pdfPath, "Rechnung_{$invoice->invoiceNumber}.pdf", 'application/pdf');

        $this->mailer->send($email);
    }
}
