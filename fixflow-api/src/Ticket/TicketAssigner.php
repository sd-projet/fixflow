<?php

namespace App\Ticket;

use App\User\{User, UserRepository};
use Doctrine\ORM\EntityManagerInterface;

/** Règle d'attribution automatique : le développeur actif ayant le moins d'incidents ouverts. */
class TicketAssigner
{
    public function __construct(private UserRepository $users, private EntityManagerInterface $em) {}

    public function pick(): ?User
    {
        $devs = $this->users->findActiveByRole('ROLE_DEV');
        if (!$devs) {
            return null;
        }

        $rows = $this->em->createQuery(
            "SELECT a.id AS uid, COUNT(t.id) AS n FROM App\Ticket\Ticket t JOIN t.assignee a
             WHERE t.archived = false AND t.status NOT IN ('resolved', 'closed') GROUP BY a.id"
        )->getArrayResult();
        $load = array_column($rows, 'n', 'uid');

        // Charge croissante, puis identifiant pour un résultat déterministe.
        usort($devs, static fn (User $x, User $y) =>
            ((int) ($load[$x->getId()] ?? 0)) <=> ((int) ($load[$y->getId()] ?? 0)) ?: $x->getId() <=> $y->getId());

        return $devs[0];
    }
}
