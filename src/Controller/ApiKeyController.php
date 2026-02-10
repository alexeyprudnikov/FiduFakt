<?php

namespace App\Controller;

use App\Entity\ApiKey;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app/api-key', name: 'app_api_key_')]
class ApiKeyController extends AbstractController
{
    /**
     * @throws RandomException
     */
    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(EntityManagerInterface $em): Response
    {
        $user = $em->getRepository(User::class)->findOneBy(['email' => $this->getUser()?->getUserIdentifier()]);
        if (!($user instanceof User)) {
            throw new NotFoundHttpException('Benutzerprofil nicht gefunden.');
        }

        $maxCount = match (true) {
            ($user->subscription === null || $user->subscription->planName === 'starter') => 1,
            default => 5
        };

        if ($user->apiKeys->count() === $maxCount) {
            $this->addFlash('error', "In diesem Paket maximal $maxCount ApiKey(s) erlaubt.");
            return $this->redirectToRoute('app_dashboard');
        }

        // 1. Key im Controller generieren
        $plainToken = 'ff_' . bin2hex(random_bytes(24));

        // 2. Entity mit dem plainToken füttern (sie hasht ihn sofort)
        $apiKey = new ApiKey(
            user: $user,
            plainToken: $plainToken
        );

        $em->persist($apiKey);
        $em->flush();

        // 3. Den plainToken per Flash-Message EINMALIG anzeigen
        $this->addFlash('api_key_success', $plainToken);

        return $this->redirectToRoute('app_dashboard', ['_fragment' => 'api-key-message']);
    }

    #[Route('/delete/{id<\d+>}', name: 'delete', methods: ['POST'])]
    public function deleteKey(
        ApiKey $apiKey,
        EntityManagerInterface $em
    ): Response
    {
        if ($apiKey->user?->getUserIdentifier() !== $this->getUser()?->getUserIdentifier()) {
            $this->addFlash('error', 'Zugriff verweigert.');
            return $this->redirectToRoute('app_dashboard');
        }
        $name = $apiKey->name;
        $em->remove($apiKey);
        $em->flush();

        $this->addFlash('success', "API-Key '$name' wurde dauerhaft gelöscht.");

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/edit-name/{id<\d+>}', name: 'edit_name', methods: ['POST'])]
    public function editName(
        ApiKey $apiKey,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if ($apiKey->user?->getUserIdentifier() !== $this->getUser()?->getUserIdentifier()) {
            $this->addFlash('error', 'Zugriff verweigert.');
            return $this->redirectToRoute('app_dashboard');
        }
        $newName = $request->request->get('name');
        if ($newName && $newName !== $apiKey->name) {
            $apiKey->name = $newName;
            $em->flush();
            $this->addFlash('success', "Name '$newName' erfolgreich gespeichert.");
        }

        return $this->redirectToRoute('app_dashboard');
    }
}
