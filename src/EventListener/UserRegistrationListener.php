<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\StripeService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Stripe\Exception\ApiErrorException;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: User::class)]
readonly class UserRegistrationListener
{
    public function __construct(
        private StripeService $stripeService
    ) {}

    /**
     * @throws ApiErrorException
     */
    public function postPersist(User $user, PostPersistEventArgs $event): void
    {
        // Erstellt den Kunden bei Stripe, sobald der User bei uns gespeichert wurde
        if (!$user->stripeCustomerId) {
            $this->stripeService->createCustomer($user);
        }
    }
}
