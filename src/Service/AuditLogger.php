<?php

namespace App\Service;

use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class AuditLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack
    ) {}

    public function log(string $action, ?string $userIdentifier, array $context = []): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $log = new AuditLog(
            action: $action,
            userIdentifier: $userIdentifier,
            context: $context,
            ipAddress: $request?->getClientIp()
        );

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
