<?php

namespace App\Notification;

use App\Ticket\Ticket;
use App\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Traitement asynchrone (Messenger) : transforme un événement de ticket en notifications. */
#[AsMessageHandler]
final class TicketEventHandler
{
    private const LABELS = [
        'critical_created' => 'INCIDENT CRITIQUE créé',
        'assigned' => 'Incident attribué',
        'commented' => 'Nouveau commentaire',
        'status_changed' => 'Statut modifié',
    ];

    public function __construct(private EntityManagerInterface $em, private UserRepository $users) {}

    public function __invoke(TicketEvent $e): void
    {
        $ticket = $this->em->find(Ticket::class, $e->ticketId);
        if (!$ticket) {
            return;
        }

        $recipients = [$ticket->getCreator(), $ticket->getAssignee()];
        if ($e->type === 'critical_created') {
            $recipients = [...$recipients, ...$this->users->findActiveByRole('ROLE_ADMIN')];
        }

        $label = self::LABELS[$e->type] ?? $e->type;
        $sent = [];
        foreach ($recipients as $u) {
            // L'auteur de l'action n'est pas notifié de sa propre action ; pas de doublon.
            if (!$u || $u->getId() === $e->actorId || isset($sent[$u->getId()])) {
                continue;
            }
            $sent[$u->getId()] = true;
            $this->em->persist(new Notification($u, $ticket, sprintf('%s — %s : %s', $label, $ticket->getReference(), $ticket->getTitle())));
        }
        $this->em->flush();
    }
}
