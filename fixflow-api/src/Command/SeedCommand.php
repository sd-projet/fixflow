<?php

namespace App\Command;

use App\Comment\Comment;
use App\Ticket\{Category, Priority, Ticket, TicketHistory, TicketStatus};
use App\User\{User, UserRepository};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand('app:seed', 'Crée des comptes et des incidents de démonstration (idempotent)')]
final class SeedCommand extends Command
{
    private const ACCOUNTS = [
        ['admin', 'Admin Demo', 'ROLE_ADMIN'],
        ['dev', 'Dev Demo', 'ROLE_DEV'],
        ['dev2', 'Camille Dev', 'ROLE_DEV'],
        ['user', 'User Demo', 'ROLE_USER'],
        ['user2', 'Sam Utilisateur', 'ROLE_USER'],
    ];

    /** [titre, catégorie, priorité, statut, dev (0|1), utilisateur (0|1), ancienneté en jours] */
    private const TICKETS = [
        ['Impossible de se connecter depuis mobile', Category::Bug, Priority::High, TicketStatus::InProgress, 0, 0, 6],
        ['Page de facturation très lente', Category::Performance, Priority::Normal, TicketStatus::New, 1, 0, 1],
        ['Faille XSS sur le champ commentaire', Category::Security, Priority::Critical, TicketStatus::New, 0, 1, 0],
        ['Export PDF : accents mal affichés', Category::Bug, Priority::Low, TicketStatus::Resolved, 1, 1, 12],
        ['Demande de nouvelle icône pour le menu', Category::Other, Priority::Low, TicketStatus::Closed, 0, 0, 20],
        ['Erreur 500 à la création d’un client', Category::Bug, Priority::High, TicketStatus::Pending, 1, 1, 8],
        ['Temps de chargement du tableau de bord', Category::Performance, Priority::Normal, TicketStatus::InProgress, 0, 1, 4],
        ['Mot de passe oublié : e-mail jamais reçu', Category::Bug, Priority::Normal, TicketStatus::Resolved, 0, 0, 15],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private UserRepository $users,
    ) { parent::__construct(); }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        $byKey = [];
        foreach (self::ACCOUNTS as [$key, $name, $role]) {
            $email = "$key@fixflow.test";
            $u = $this->users->findOneBy(['email' => $email]);
            if (!$u) {
                $u = (new User())->setEmail($email)->setFullName($name)->setRoles([$role]);
                $u->setPassword($this->hasher->hashPassword($u, 'password'));
                $this->em->persist($u);
            }
            $byKey[$key] = $u;
        }

        $created = 0;
        if ($this->em->getRepository(Ticket::class)->count([]) === 0) {
            $devs = [$byKey['dev'], $byKey['dev2']];
            $people = [$byKey['user'], $byKey['user2']];
            foreach (self::TICKETS as $i => [$title, $cat, $prio, $status, $d, $p, $age]) {
                $createdAt = new \DateTimeImmutable("-$age days");
                $updatedAt = $status === TicketStatus::New ? $createdAt : $createdAt->modify('+1 day');
                $t = (new Ticket())
                    ->setReference(sprintf('FF-%s-DEMO%02d', date('Y'), $i + 1))
                    ->setTitle($title)->setDescription("Description détaillée de l'incident « $title ». Étapes pour reproduire, environnement, capture d'écran à fournir.")
                    ->setCategory($cat)->setPriority($prio)->setStatus($status)
                    ->setCreator($people[$p])->setAssignee($devs[$d])
                    ->setTimestamps($createdAt, $updatedAt);
                if (in_array($status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
                    $t->setResolvedAt($updatedAt);
                }
                $this->em->persist($t);
                $this->em->persist(new TicketHistory($t, $byKey['admin'], 'created', null, $t->getReference()));
                if ($status !== TicketStatus::New) {
                    $this->em->persist(new TicketHistory($t, $devs[$d], 'status', 'new', $status->value));
                    $c = (new Comment())->setTicket($t)->setAuthor($devs[$d])->setContent('Pris en charge, je regarde cela.');
                    $this->em->persist($c);
                }
                $created++;
            }
        }
        $this->em->flush();

        $out->writeln(sprintf('Comptes prêts (mot de passe : password). %d incident(s) de démonstration créé(s).', $created));
        return Command::SUCCESS;
    }
}
