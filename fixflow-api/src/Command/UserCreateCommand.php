<?php

namespace App\Command;

use App\User\{User, UserRepository};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand('app:user:create', 'Crée un compte (sans effet si l’email existe déjà) — utilisé pour le premier admin en production')]
final class UserCreateCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private UserRepository $users,
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('fullName', InputArgument::REQUIRED)
            ->addArgument('role', InputArgument::OPTIONAL, 'ROLE_ADMIN | ROLE_DEV | ROLE_USER', 'ROLE_USER')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Mot de passe (8 caractères minimum)');
    }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        $email = (string) $in->getArgument('email');
        $role = (string) $in->getArgument('role');
        $password = (string) $in->getOption('password');

        if (!in_array($role, User::ROLES, true)) {
            $out->writeln('<error>Rôle invalide. Valeurs : ' . implode(', ', User::ROLES) . '</error>');
            return Command::INVALID;
        }
        if (strlen($password) < 8) {
            $out->writeln('<error>--password est obligatoire (8 caractères minimum).</error>');
            return Command::INVALID;
        }
        if ($this->users->findOneBy(['email' => $email])) {
            $out->writeln("Le compte $email existe déjà : rien à faire.");
            return Command::SUCCESS;
        }

        $user = (new User())->setEmail($email)->setFullName((string) $in->getArgument('fullName'))->setRoles([$role]);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();
        $out->writeln("Compte $email créé ($role).");
        return Command::SUCCESS;
    }
}
