<?php

namespace App\Command;

use App\Notification\Notification;
use App\Ticket\Ticket;
use App\User\{User, UserRepository};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:tickets:alerts', 'Détecte les tickets inactifs et les critiques non traités (à planifier en cron)')]
final class AlertsCommand extends Command
{
    /** @var array<string, true> alertes déjà créées pendant cette exécution (pas encore flushées) */
    private array $queued = [];

    public function __construct(private EntityManagerInterface $em, private UserRepository $users) { parent::__construct(); }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Jours sans activité avant alerte', 3)
            ->addOption('critical-hours', null, InputOption::VALUE_REQUIRED, 'Heures avant alerte sur un incident critique resté « nouveau »', 1);
    }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        $staleLimit = new \DateTimeImmutable('-' . (int) $in->getOption('days') . ' days');
        $criticalLimit = new \DateTimeImmutable('-' . (int) $in->getOption('critical-hours') . ' hours');
        $admins = $this->users->findActiveByRole('ROLE_ADMIN');

        $stale = $this->em->createQuery(
            "SELECT t FROM App\Ticket\Ticket t WHERE t.archived = false AND t.status IN ('new', 'in_progress', 'pending') AND t.updatedAt < :l"
        )->setParameter('l', $staleLimit)->getResult();
        $critical = $this->em->createQuery(
            "SELECT t FROM App\Ticket\Ticket t WHERE t.archived = false AND t.priority = 'critical' AND t.status = 'new' AND t.createdAt < :l"
        )->setParameter('l', $criticalLimit)->getResult();

        $this->queued = [];
        $created = 0;
        foreach ($stale as $t) {
            foreach (array_filter([$t->getAssignee(), ...$admins]) as $u) {
                $created += $this->notify($u, $t, "Inactif depuis plusieurs jours : {$t->getReference()}");
            }
        }
        foreach ($critical as $t) {
            foreach ($admins as $u) {
                $created += $this->notify($u, $t, "Critique non traité : {$t->getReference()}");
            }
        }
        $this->em->flush();

        $out->writeln(sprintf('%d inactifs, %d critiques non traités, %d notification(s) créée(s).', count($stale), count($critical), $created));
        return Command::SUCCESS;
    }

    /** Évite de renvoyer la même alerte tant que la précédente n'a pas été lue (cron quotidien). */
    private function notify(User $u, Ticket $t, string $message): int
    {
        $pending = (int) $this->em->createQuery(
            'SELECT COUNT(n.id) FROM App\Notification\Notification n WHERE n.recipient = :u AND n.ticket = :t AND n.message = :m AND n.read = false'
        )->setParameter('u', $u)->setParameter('t', $t)->setParameter('m', $message)->getSingleScalarResult();
        $key = $u->getId() . '|' . $t->getId() . '|' . $message;
        if ($pending > 0 || isset($this->queued[$key])) {
            return 0;
        }
        $this->queued[$key] = true;
        $this->em->persist(new Notification($u, $t, $message));
        return 1;
    }
}
