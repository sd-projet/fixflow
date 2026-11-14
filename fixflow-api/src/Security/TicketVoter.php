<?php
namespace App\Security;

use App\Ticket\{Ticket, TicketStatus};
use App\User\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class TicketVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, ['TICKET_VIEW', 'TICKET_EDIT'], true) && $subject instanceof Ticket;
    }

    protected function voteOnAttribute(string $attribute, mixed $ticket, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) return false;
        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true)) return true;
        if (in_array('ROLE_DEV', $roles, true)) return $ticket->getAssignee()?->getId() === $user->getId();
        $own = $ticket->getCreator()?->getId() === $user->getId();
        return $attribute === 'TICKET_VIEW' ? $own : ($own && $ticket->getStatus() !== TicketStatus::Closed);
    }
}
