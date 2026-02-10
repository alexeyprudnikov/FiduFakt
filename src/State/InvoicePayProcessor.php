<?php

namespace App\State;

use App\Entity\Invoice;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Service\InvoiceWorkflowService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class InvoicePayProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ProcessorInterface $persistProcessor,
        private InvoiceWorkflowService $workflowService,
    ) {}

    /**
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $securityUser = $this->security->getUser();
        if (!$securityUser) {
            throw new AccessDeniedException('Nicht authentifiziert.');
        }
        if ($data instanceof Invoice) {
            if ($data->user->getUserIdentifier() !== $securityUser->getUserIdentifier()) {
                throw new AccessDeniedException('Nicht authentifiziert.');
            }
            $this->workflowService->markAsPaid($data);
        }
        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
