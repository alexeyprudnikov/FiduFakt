<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\LoginLink\LoginLinkNotification;

class SecurityController extends AbstractController
{
    #[Route('/register', name: 'register')]
    public function register(
        Request $request,
        UserRepository $userRepo,
        EntityManagerInterface $em,
        LoginLinkHandlerInterface $loginLinkHandler,
        NotifierInterface $notifier
    ): Response {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $user = $userRepo->findOneBy(['email' => $email]);

            if (!$user) {
                $user = new User();
                $user->email = $email;
                $em->persist($user);
                $em->flush();
            }

            $loginLinkDetails = $loginLinkHandler->createLoginLink($user);

            $notification = new LoginLinkNotification(
                $loginLinkDetails,
                'Willkommen bei FiduFakt - Bestätige deinen Account'
            );
            $notifier->send($notification, new Recipient($user->email));

            return $this->render('registration/check_email.html.twig', [
                'email' => $email
            ]);
        }

        return $this->render('registration/register.html.twig');
    }

    #[Route('/login', name: 'login')]
    public function requestLoginLink(
        NotifierInterface $notifier,
        LoginLinkHandlerInterface $loginLinkHandler,
        UserRepository $userRepository,
        Request $request
    ): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->getPayload()->get('email');
            $user = $userRepository->findOneBy(['email' => $email]);

            if (!$user) {
                return $this->redirectToRoute('register');
            }

            $loginLinkDetails = $loginLinkHandler->createLoginLink($user);

            $notification = new LoginLinkNotification(
                $loginLinkDetails,
                'Willkommen bei FiduFakt!'
            );
            $recipient = new Recipient($user->email);
            $notifier->send($notification, $recipient);

            return $this->render('security/login_link_sent.html.twig');
        }

        return $this->render('security/request_login_link.html.twig');
    }

    #[Route('/login_check', name: 'login_check')]
    public function check(Request $request): Response
    {
        // get the login link query parameters
        $expires = $request->query->get('expires');
        $username = $request->query->get('user');
        $hash = $request->query->get('hash');

        // and render a template with the button
        return $this->render('security/process_login_link.html.twig', [
            'expires' => $expires,
            'user' => $username,
            'hash' => $hash,
        ]);
    }
}
