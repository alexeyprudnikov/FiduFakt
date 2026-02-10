<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

readonly class InfoProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
    ) {}

    /**
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $securityUser = $this->security->getUser();
        if ($securityUser === null) {
            throw new AccessDeniedHttpException();
        }
        return new JsonResponse([
            'message' => 'Willkommen bei FiduFakt',
            'user' => $securityUser->getUserIdentifier(),
        ]);
    }
}
