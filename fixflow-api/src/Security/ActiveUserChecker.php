<?php
namespace App\Security;

use App\User\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\{UserCheckerInterface, UserInterface};

final class ActiveUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && !$user->isActive()) throw new CustomUserMessageAccountStatusException('Compte désactivé.');
    }
    public function checkPostAuth(UserInterface $user): void {}
}
