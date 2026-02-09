<?php

namespace App\State;

use App\Entity\Invoice;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\UserRepository;
use App\Service\InvoiceEngine;
use App\Service\InvoiceStorageService;
use App\Service\InvoiceWorkflowService;
use JsonException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

readonly class InvoiceProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private UserRepository $userRepository,
        private ProcessorInterface $persistProcessor,
        private Environment $twig,
        private InvoiceEngine $invoiceEngine,
        private InvoiceStorageService $storageService,
        private InvoiceWorkflowService $workflowService,
    ) {}

    /**
     * @throws JsonException
     * @throws TransportExceptionInterface
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $securityUser = $this->security->getUser();
        if (!$securityUser) {
            throw new AccessDeniedException('Nicht authentifiziert.');
        }
        $user = $this->userRepository->findOneBy(['email' => $securityUser->getUserIdentifier()]);
        if (!$user) {
            throw new NotFoundHttpException('Benutzerprofil nicht gefunden.');
        }
        if (!$user->hasQuotaLeft()) {
            throw new HttpException(402, 'Limit erreicht. Bitte einen Plan wählen oder upgraden.');
        }
        if (!$data instanceof Invoice) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        // 1. Ersteller setzten und Payload absichern (Hash generieren)
        $data
            ->setUser($user)
            ->securePayload();

        // 2. In der Datenbank speichern (damit wir eine ID und ein Datum haben)
        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        // keine Archivierung für Sandbox
        if ($user->subscription !== null) {
            // 3. Dokumente generieren (XML & PDF)
            try {
                $html = $this->twig->render('pdf/invoice.html.twig', [
                    'invoice' => $data,
                ]);
            } catch (LoaderError|RuntimeError|SyntaxError $e) {
                $html = "<h1>Rechnung {$data->invoiceNumber}</h1>";
            }
            $xml = $this->invoiceEngine->generateZugferdXml($data);
            $pdfContent = $this->invoiceEngine->createZugferdPdf($html, $xml, $data);

            // 4. Archivieren (Speichern & File-Hash)
            $this->storageService->archiveInvoice($data, $pdfContent);
        }

        // 5. WORKFLOW TRIGGERN: Hier kommt dein Aufruf!
        // Stellt die Rechnung aus und versendet die Mail.
        $this->workflowService->issue($data);

        return $result;
    }
}
