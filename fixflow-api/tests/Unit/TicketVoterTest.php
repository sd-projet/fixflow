<?php

namespace App\Tests\Unit;

use App\Security\TicketVoter;
use App\Ticket\{Ticket, TicketStatus};
use App\User\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class TicketVoterTest extends TestCase
{
    private static function user(int $id, string $role): User
    {
        $u = (new User())->setEmail("u$id@test.local")->setRoles([$role]);
        (new \ReflectionProperty(User::class, 'id'))->setValue($u, $id);
        return $u;
    }

    private static function vote(User $who, Ticket $t, string $attr): int
    {
        return (new TicketVoter())->vote(new UsernamePasswordToken($who, 'main', $who->getRoles()), $t, [$attr]);
    }

    public static function matrix(): iterable
    {
        $admin = self::user(1, 'ROLE_ADMIN');
        $dev = self::user(2, 'ROLE_DEV');
        $otherDev = self::user(3, 'ROLE_DEV');
        $owner = self::user(4, 'ROLE_USER');
        $stranger = self::user(5, 'ROLE_USER');

        $open = (new Ticket())->setCreator($owner)->setAssignee($dev);
        $closed = (new Ticket())->setCreator($owner)->setAssignee($dev)->setStatus(TicketStatus::Closed);

        yield 'admin voit' => [$admin, $open, 'TICKET_VIEW', true];
        yield 'admin modifie' => [$admin, $closed, 'TICKET_EDIT', true];
        yield 'dev assigné voit' => [$dev, $open, 'TICKET_VIEW', true];
        yield 'dev assigné modifie' => [$dev, $open, 'TICKET_EDIT', true];
        yield 'autre dev ne voit pas' => [$otherDev, $open, 'TICKET_VIEW', false];
        yield 'créateur voit' => [$owner, $open, 'TICKET_VIEW', true];
        yield 'créateur modifie un ticket ouvert' => [$owner, $open, 'TICKET_EDIT', true];
        yield 'créateur ne modifie pas un ticket fermé' => [$owner, $closed, 'TICKET_EDIT', false];
        yield 'étranger ne voit pas' => [$stranger, $open, 'TICKET_VIEW', false];
    }

    #[DataProvider('matrix')]
    public function testVoteMatrix(User $who, Ticket $ticket, string $attribute, bool $granted): void
    {
        $expected = $granted ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED;
        $this->assertSame($expected, self::vote($who, $ticket, $attribute));
    }

    public function testAbstainsOnUnknownAttributeOrSubject(): void
    {
        $u = self::user(1, 'ROLE_ADMIN');
        $voter = new TicketVoter();
        $token = new UsernamePasswordToken($u, 'main', $u->getRoles());
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, new Ticket(), ['SOMETHING_ELSE']));
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, new \stdClass(), ['TICKET_VIEW']));
    }
}
