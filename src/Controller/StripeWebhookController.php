<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Entity\Subscription;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StripeWebhookController extends AbstractController
{
    #[Route('/webhook/stripe', name: 'stripe_webhook', methods: ['POST'])]
    public function handle(
        string $stripeWebhookSecret,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');

        try {
            // Verifiziert, dass der Request echt ist
            $event = Webhook::constructEvent(
                $payload, $sigHeader, $stripeWebhookSecret
            );
        } catch (\UnexpectedValueException | SignatureVerificationException $e) {
            return new Response('Invalid signature', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $subscriptionSObj = $event->data->object;

            // 1. User über die ID finden, die wir beim Registrieren gespeichert haben
            $user = $userRepository->findOneBy(['stripeCustomerId' => $subscriptionSObj->customer]);

            if ($user) {
                // 2. Den Plan identifizieren
                // Wir haben beim Erstellen der Session den Plan in metadata['plan_name'] gespeichert
                $planName = $subscriptionSObj->metadata->plan_name ?? 'starter';

                $limit = match($planName) {
                    'business' => 500,
                    'enterprise' => 10000,
                    default => 50, // starter
                };

                // 3. Subscription aktualisieren oder erstellen
                $subscription = $user->subscription;

                if (!$subscription) {
                    $subscription = new Subscription(
                        user: $user,
                        stripeSubscriptionId: $subscriptionSObj->subscription,
                        invoiceLimit: $limit,
                        planName: $planName
                    );
                    // falls keine Subscription gab, invoiceCount zurücksetzen
                    // todo: evtl. zurücksetzen auch bei einem neuen Plan?
                    $user->resetInvoiceCount();
                } else {
                    $subscription->stripeSubscriptionId = $subscriptionSObj->subscription; // Wichtig für Kündigungen!
                    $subscription->invoiceLimit = $limit;
                }

                $em->persist($subscription);
                $em->flush();
            }
        }

        if ($event->type === 'customer.subscription.deleted') {
            $subscriptionSObj = $event->data->object; // Das Stripe Subscription Objekt
            $stripeCustomerId = $subscriptionSObj->customer;

            $user = $userRepository->findOneBy(['stripeCustomerId' => $stripeCustomerId]);

            if ($user && $user->subscription) {
                // Option A: Subscription komplett löschen
                $em->remove($user->subscription);

                // Option B: Nur Limit auf 0 setzen (besser für Statistiken)
                // $user->subscription->invoiceLimit = 0;

                $em->flush();
            }
        }

        return $this->json(['status' => 'success']);
    }
}
