<?php

namespace App\Notification;

use ApiPlatform\Metadata\{ApiResource, GetCollection, Patch};
use App\Ticket\Ticket;
use App\User\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ApiResource(
    operations: [new GetCollection(), new Patch(security: "object.getRecipient() == user")],
    normalizationContext: ['groups' => ['notif:read']], denormalizationContext: ['groups' => ['notif:write']],
    order: ['createdAt' => 'DESC'],
)]
class Notification
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] #[Groups(['notif:read'])]
    private ?int $id = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)]
    private User $recipient;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] #[Groups(['notif:read'])]
    private Ticket $ticket;
    #[ORM\Column] #[Groups(['notif:read'])]
    private string $message;
    /** Exposé sous le nom `read` (accesseur isRead/setRead). */
    #[ORM\Column(name: 'is_read')] #[Groups(['notif:read', 'notif:write'])]
    private bool $read = false;
    #[ORM\Column] #[Groups(['notif:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $r, Ticket $t, string $m)
    {
        $this->recipient = $r; $this->ticket = $t; $this->message = $m; $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getRecipient(): User { return $this->recipient; }
    public function getTicket(): Ticket { return $this->ticket; }
    public function getMessage(): string { return $this->message; }
    public function isRead(): bool { return $this->read; }
    public function setRead(bool $v): static { $this->read = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
