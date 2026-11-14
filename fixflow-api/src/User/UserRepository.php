<?php

namespace App\User;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Utilisateurs actifs ayant un rôle donné.
     * Le filtrage se fait en PHP : la colonne `roles` est de type JSON, sur lequel PostgreSQL
     * n'accepte pas l'opérateur LIKE.
     *
     * @return User[]
     */
    public function findActiveByRole(string $role): array
    {
        $users = $this->findBy(['active' => true], ['id' => 'ASC']);

        return array_values(array_filter($users, static fn (User $u) => in_array($role, $u->getRoles(), true)));
    }
}
