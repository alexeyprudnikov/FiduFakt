<?php

namespace App\Security\Voter;

use App\Entity\Invoice;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\User\UserInterface;

class InvoiceVoter extends Voter
{
    public const string VIEW = 'INVOICE_VIEW';
    public const string PATCH = 'INVOICE_PATCH';

    public function __construct(
        private readonly Security $security
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof Invoice;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return false;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        /** @var Invoice $invoice */
        $invoice = $subject;

        return $this->canView($invoice, $user);
    }

    private function canView(Invoice $invoice, UserInterface $user): bool
    {
        return $invoice->user?->email === $user->getUserIdentifier();
    }
}
