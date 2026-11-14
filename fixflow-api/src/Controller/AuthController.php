<?php
namespace App\Controller;

use App\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AuthController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(#[CurrentUser] ?User $user): JsonResponse { return $this->me($user); }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) return new JsonResponse(['error' => 'Non authentifié'], 401);
        return new JsonResponse(['id' => $user->getId(), 'email' => $user->getEmail(), 'fullName' => $user->getFullName(), 'roles' => $user->getRoles()]);
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): never { throw new \LogicException(); }
}
