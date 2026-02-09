<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\StripeService;
use Stripe\Exception\ApiErrorException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app/payment', name: 'app_payment_')]
class StripeController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly StripeService $stripeService
    )
    {
    }
    /**
     * @throws ApiErrorException
     */
    #[Route('/plan/{plan}', name: 'plan')]
    public function checkout(
        string $plan,
        Request $request,
    ): Response
    {
        $user = $this->userRepository->findOneBy(['email' => $this->getUser()?->getUserIdentifier()]);
        if (!($user instanceof User)) {
            throw new NotFoundHttpException('Benutzerprofil nicht gefunden.');
        }
        $cycle = $request->query->get('cycle', 'monthly');
        $stripeUrl = $this->stripeService->createCheckoutSession($user, $plan, $cycle);
        return $this->redirect($stripeUrl, 303);
    }

    #[Route('/checkout/success', name: 'checkout_success')]
    public function success(): Response
    {
        $this->addFlash('success', 'Vielen Dank! Ihr Abo wird gerade aktiviert.');
        return $this->redirectToRoute('app_dashboard');
    }

    /**
     * @throws ApiErrorException
     */
    #[Route('/billing', name: 'billing')]
    public function customerPortal(
    ): Response
    {
        $user = $this->userRepository->findOneBy(['email' => $this->getUser()?->getUserIdentifier()]);
        if (!($user instanceof User)) {
            throw new NotFoundHttpException('Benutzerprofil nicht gefunden.');
        }
        $stripeUrl = $this->stripeService->createBillingPortalSession($user);
        return $this->redirect($stripeUrl);
    }
}
