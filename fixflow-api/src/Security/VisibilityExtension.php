<?php

namespace App\Security;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Comment\Comment;
use App\Notification\Notification;
use App\Ticket\{Ticket, TicketHistory};
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Filtre côté serveur toutes les collections selon le rôle de l'utilisateur :
 *  - admin : tout voir ; développeur : tickets qui lui sont assignés ; utilisateur : ses propres tickets ;
 *  - notifications : uniquement celles du destinataire ;
 *  - tickets archivés : masqués sauf si `archived=true` est demandé explicitement.
 */
final class VisibilityExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private Security $security) {}

    public function applyToCollection(QueryBuilder $qb, QueryNameGeneratorInterface $g, string $class, ?Operation $op = null, array $context = []): void
    {
        $a = $qb->getRootAliases()[0];
        $user = $this->security->getUser();

        if ($class === Notification::class) {
            $qb->andWhere("$a.recipient = :vu")->setParameter('vu', $user);
            return;
        }

        if ($class === Ticket::class && !isset($context['filters']['archived'])) {
            $qb->andWhere("$a.archived = false");
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        if ($class === Ticket::class) {
            $t = $a;
        } elseif ($class === Comment::class || $class === TicketHistory::class) {
            $qb->join("$a.ticket", 'vt');
            $t = 'vt';
        } else {
            return;
        }

        $field = $this->security->isGranted('ROLE_DEV') ? 'assignee' : 'creator';
        $qb->andWhere("$t.$field = :vu")->setParameter('vu', $user);
    }
}
