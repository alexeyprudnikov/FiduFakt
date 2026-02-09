<?php

namespace App\Security;

use App\Repository\ApiKeyRepository;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class ApiKeyAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly ApiKeyRepository $apiKeyRepository) {}

    public function supports(Request $request): ?bool
    {
        // Der Authenticator springt an, wenn der Header gesetzt ist
        return $request->headers->has('X-API-KEY');
    }

    public function authenticate(Request $request): Passport
    {
        $apiToken = $request->headers->get('X-API-KEY');
        if (null === $apiToken) {
            throw new CustomUserMessageAuthenticationException('Kein API-Key gefunden.');
        }

        // 1. Wir suchen über den Prefix (die ersten 10 Zeichen), um die DB-Suche zu beschleunigen
        $prefix = substr($apiToken, 0, 10);
        $apiKey = $this->apiKeyRepository->findOneBy(['prefix' => $prefix]);

        if (!$apiKey) {
            throw new CustomUserMessageAuthenticationException('Ungültiger API-Key.');
        }

        // 2. Wir vergleichen den Full-Token mit dem gespeicherten Hash
        if (!password_verify($apiToken, $apiKey->tokenHash)) {
            throw new CustomUserMessageAuthenticationException('Ungültiger API-Key.');
        }

        $apiKey->lastUsedAt = new DateTimeImmutable();
        $this->apiKeyRepository->save($apiKey, true);

        // 3. Wenn alles passt, laden wir den zugehörigen User
        return new SelfValidatingPassport(new UserBadge($apiKey->user->getUserIdentifier()));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // Einfach weiterlaufen lassen zum Controller
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => $exception->getMessageKey()], Response::HTTP_UNAUTHORIZED);
    }
}
