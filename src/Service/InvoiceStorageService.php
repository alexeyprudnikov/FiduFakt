<?php

namespace App\Service;

use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;

readonly class InvoiceStorageService
{
    public function __construct(
        private string                 $projectDir,
        private EntityManagerInterface $entityManager
    ) {}

    public function archiveInvoice(Invoice $invoice, string $pdfContent): void
    {
        $subPath = $invoice->createdAt->format('Y/m');
        $storageDir = $this->projectDir . "/var/storage/invoices/$subPath";
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }
        $filename = sprintf('%s_%s.pdf', $invoice->invoiceNumber, $invoice->id->toBase58());
        $fullPath = $storageDir . '/' . $filename;
        file_put_contents($fullPath, $pdfContent);
        $hash = hash('sha256', $pdfContent);
        $invoice
            ->markAsArchived($subPath . '/' . $filename)
            ->setFileHash($hash);

        $this->entityManager->flush();
    }
}
