<?php

namespace App\Ticket;

use ApiPlatform\Metadata\{Operation, Post};
use ApiPlatform\State\ProcessorInterface;
use App\Notification\TicketEvent;
use App\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, UnprocessableEntityHttpException};
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Règles métier des incidents : matrice de droits par rôle, attribution automatique,
 * historisation des changements et émission des événements de notification.
 */
final class TicketProcessor implements ProcessorInterface
{
    /** Champs modifiables par rôle (null = tous). */
    private const EDITABLE_BY_DEV = ['status'];
    private const EDITABLE_BY_USER = ['title', 'description'];

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')] private ProcessorInterface $persist,
        private Security $security,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private TicketAssigner $assigner,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Ticket
    {
        /** @var Ticket $data */
        /** @var User $user */
        $user = $this->security->getUser();
        $admin = $this->security->isGranted('ROLE_ADMIN');
        $creation = $operation instanceof Post;
        $events = [];
        $changes = [];

        if ($creation) {
            $data->setCreator($user)->setReference(sprintf('FF-%s-%s', date('Y'), strtoupper(bin2hex(random_bytes(3)))));
            if (!$admin) {
                $data->setStatus(TicketStatus::New)->setAssignee(null)->setArchived(false);
            }
            if ($data->getAssignee()) {
                $this->assertAssignable($data->getAssignee());
            } else {
                $data->setAssignee($this->assigner->pick());
            }
            $this->syncResolvedAt($data);
            $events[] = match (true) {
                $data->getPriority() === Priority::Critical => 'critical_created',
                $data->getAssignee() !== null => 'assigned',
                default => null,
            };
        } else {
            $changes = $this->diff($context['previous_data'], $data);
            $this->assertAllowed($changes, $admin);

            if (isset($changes['assignee']) && $data->getAssignee()) {
                $this->assertAssignable($data->getAssignee());
            }
            if (isset($changes['status'])) {
                $this->syncResolvedAt($data);
                $events[] = 'status_changed';
            }
            if (isset($changes['assignee']) && $data->getAssignee()) {
                $events[] = 'assigned';
            }
        }

        $result = $this->persist->process($data, $operation, $uriVariables, $context);

        if ($creation) {
            $this->em->persist(new TicketHistory($data, $user, 'created', null, $data->getReference()));
            $this->em->flush();
        } elseif ($changes) {
            foreach ($changes as $field => [$old, $new]) {
                $this->em->persist(new TicketHistory($data, $user, $field, $old, $new));
            }
            $this->em->flush();
        }

        foreach (array_filter($events) as $e) {
            $this->bus->dispatch(new TicketEvent($data->getId(), $e, $user->getId()));
        }

        return $result;
    }

    /** Valeurs avant/après pour chaque champ suivi qui a changé. */
    private function diff(Ticket $old, Ticket $new): array
    {
        return array_filter([
            'title' => [$old->getTitle(), $new->getTitle()],
            'description' => [$old->getDescription(), $new->getDescription()],
            'status' => [$old->getStatus()->value, $new->getStatus()->value],
            'priority' => [$old->getPriority()->value, $new->getPriority()->value],
            'category' => [$old->getCategory()->value, $new->getCategory()->value],
            'assignee' => [$old->getAssignee()?->getFullName(), $new->getAssignee()?->getFullName()],
            'archived' => [$old->isArchived() ? 'oui' : 'non', $new->isArchived() ? 'oui' : 'non'],
        ], static fn (array $p) => $p[0] !== $p[1]);
    }

    /** Admin : tout ; développeur : statut uniquement ; utilisateur (créateur) : titre et description. */
    private function assertAllowed(array $changes, bool $admin): void
    {
        if ($admin) {
            return;
        }
        $allowed = $this->security->isGranted('ROLE_DEV') ? self::EDITABLE_BY_DEV : self::EDITABLE_BY_USER;
        if (array_diff(array_keys($changes), $allowed)) {
            throw new AccessDeniedHttpException('Modification non autorisée pour votre rôle.');
        }
    }

    private function assertAssignable(User $dev): void
    {
        if (!$dev->isActive() || !in_array('ROLE_DEV', $dev->getRoles(), true)) {
            throw new UnprocessableEntityHttpException("L'incident ne peut être attribué qu'à un développeur actif.");
        }
    }

    /** Date de résolution conservée en passant de « résolu » à « fermé », effacée à la réouverture. */
    private function syncResolvedAt(Ticket $t): void
    {
        $done = in_array($t->getStatus(), [TicketStatus::Resolved, TicketStatus::Closed], true);
        $t->setResolvedAt($done ? ($t->getResolvedAt() ?? new \DateTimeImmutable()) : null);
    }
}
