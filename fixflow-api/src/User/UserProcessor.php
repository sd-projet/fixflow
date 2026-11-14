<?php

namespace App\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Règles métier sur les comptes : seul un admin gère rôles et activation, hachage du mot de passe. */
final class UserProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')] private ProcessorInterface $persist,
        private UserPasswordHasherInterface $hasher,
        private Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        /** @var User $data */
        $prev = $context['previous_data'] ?? null;
        $current = $this->security->getUser();

        if ($prev instanceof User) {
            $privilegedChange = $data->getRoles() !== $prev->getRoles() || $data->isActive() !== $prev->isActive();
            if ($privilegedChange && !$this->security->isGranted('ROLE_ADMIN')) {
                throw new AccessDeniedHttpException('Seul un administrateur peut modifier rôles et activation.');
            }
            // Un admin ne peut pas se retirer lui-même l'accès (évite de verrouiller la plateforme).
            if ($current instanceof User && $current->getId() === $prev->getId()
                && (!$data->isActive() || !in_array('ROLE_ADMIN', $data->getRoles(), true))) {
                throw new AccessDeniedHttpException('Vous ne pouvez pas désactiver votre compte ni retirer votre rôle administrateur.');
            }
        }

        if ($data->getPlainPassword()) {
            $data->setPassword($this->hasher->hashPassword($data, $data->getPlainPassword()));
            $data->eraseCredentials();
        }

        return $this->persist->process($data, $operation, $uriVariables, $context);
    }
}
