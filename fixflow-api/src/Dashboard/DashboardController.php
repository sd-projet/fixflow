<?php

namespace App\Dashboard;

use App\Ticket\Ticket;
use App\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Tableau de bord adapté au rôle : l'admin voit tout, le développeur ses tickets, l'utilisateur les siens. */
final class DashboardController extends AbstractController
{
    private const OPEN = "t.status IN ('new', 'in_progress', 'pending')";

    #[Route('/api/dashboard', name: 'api_dashboard', methods: ['GET'])]
    public function __invoke(#[CurrentUser] User $user, EntityManagerInterface $em): JsonResponse
    {
        $admin = $this->isGranted('ROLE_ADMIN');
        $scope = $admin ? 't.archived = false' : 't.archived = false AND ' . ($this->isGranted('ROLE_DEV') ? 't.assignee = :u' : 't.creator = :u');

        $query = function (string $dql) use ($em, $admin, $user): Query {
            $q = $em->createQuery($dql);
            if (!$admin) {
                $q->setParameter('u', $user);
            }
            return $q;
        };
        $count = fn (string $where) => (int) $query("SELECT COUNT(t.id) FROM App\Ticket\Ticket t WHERE $scope AND $where")->getSingleScalarResult();
        $group = fn (string $field) => array_map(
            static fn (array $r) => ['key' => $r['k'] instanceof \BackedEnum ? $r['k']->value : $r['k'], 'count' => (int) $r['n']],
            $query("SELECT t.$field AS k, COUNT(t.id) AS n FROM App\Ticket\Ticket t WHERE $scope GROUP BY t.$field")->getResult()
        );
        $list = fn (string $where, string $order, int $max) => array_map(
            static fn (Ticket $t) => [
                'id' => $t->getId(), 'reference' => $t->getReference(), 'title' => $t->getTitle(),
                'status' => $t->getStatus()->value, 'priority' => $t->getPriority()->value,
                'updatedAt' => $t->getUpdatedAt()->format(\DATE_ATOM),
            ],
            $query("SELECT t FROM App\Ticket\Ticket t WHERE $scope AND $where ORDER BY $order")->setMaxResults($max)->getResult()
        );

        $avg = null;
        if ($admin) {
            $table = $em->getClassMetadata(Ticket::class)->getTableName();
            $raw = $em->getConnection()->fetchOne("SELECT AVG(EXTRACT(EPOCH FROM (resolved_at - created_at))) / 3600 FROM $table WHERE resolved_at IS NOT NULL AND archived = false");
            $avg = $raw !== null && $raw !== false ? round((float) $raw, 1) : null;
        }

        return $this->json([
            'total' => $count('1 = 1'),
            'open' => $count(self::OPEN),
            'inProgress' => $count("t.status = 'in_progress'"),
            'pending' => $count("t.status = 'pending'"),
            'critical' => $count("t.priority = 'critical' AND t.status NOT IN ('resolved', 'closed')"),
            'resolved' => $count("t.status IN ('resolved', 'closed')"),
            'byStatus' => $group('status'),
            'byPriority' => $group('priority'),
            'avgResolutionHours' => $avg,
            // « Incidents prioritaires » : ouverts et de priorité haute ou critique.
            'urgent' => $list(self::OPEN . " AND t.priority IN ('high', 'critical')", 't.updatedAt DESC', 5),
            // « Incidents récemment modifiés » / « Dernières mises à jour ».
            'recent' => $list('1 = 1', 't.updatedAt DESC', 5),
        ]);
    }
}
