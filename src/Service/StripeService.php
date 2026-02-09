<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StripeService
{
    private StripeClient $stripe;

    public function __construct(
        string                                  $stripeSecretKey,
        private readonly string                 $starterMonthlyId,
        private readonly string                 $starterYearlyId,
        private readonly string                 $businessMonthlyId,
        private readonly string                 $businessYearlyId,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface  $urlGenerator,
    ) {
        $this->stripe = new StripeClient($stripeSecretKey);
    }

    /**
     * @throws ApiErrorException
     */
    public function createCustomer(User $user): string
    {
        $customer = $this->stripe->customers->create([
            'email' => $user->email,
            'metadata' => [
                'app_user_id' => $user->id
            ]
        ]);

        $user->stripeCustomerId = $customer->id;
        $this->entityManager->flush();

        return $customer->id;
    }

    /**
     * @throws ApiErrorException
     */
    public function createCheckoutSession(User $user, string $plan, string $cycle = 'monthly'): string
    {
        // Mapping der Pläne zu deinen Stripe Price-IDs
        // Diese IDs kopierst du aus deinem Stripe-Dashboard (Produkte -> Preis-ID)
        $prices = [
            'starter' => [
                'monthly' => $this->starterMonthlyId,
                'yearly'  => $this->starterYearlyId,
            ],
            'business' => [
                'monthly' => $this->businessMonthlyId,
                'yearly'  => $this->businessYearlyId,
            ],
        ];
        // Sicherheitshalber Fallback auf Starter-Monatlich, falls Blödsinn übergeben wird
        $priceId = $prices[$plan][$cycle] ?? $prices['starter']['monthly'];
        $session = $this->stripe->checkout->sessions->create([
            'customer' => $user->stripeCustomerId, // Wir nutzen die ID vom RegistrationListener!
            'mode' => 'subscription',
            'payment_method_types' => ['card', 'sepa_debit'],
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'metadata' => [
                'plan_name' => $plan,
                'billing_cycle' => $cycle
            ],
            'success_url' => $this->urlGenerator->generate('app_payment_checkout_success', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'cancel_url' => $this->urlGenerator->generate('app_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
        return $session->url;
    }

    /**
     * @throws ApiErrorException
     */
    public function createBillingPortalSession(User $user): string
    {
        $session = $this->stripe->billingPortal->sessions->create([
            'customer' => $user->stripeCustomerId,
            'return_url' => $this->urlGenerator->generate('dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
        return $session->url;
    }
}
