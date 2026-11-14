<?php

namespace App\Ticket;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\{ApiFilter, ApiResource, Get, GetCollection};
use App\User\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/** Journal d'audit : qui a modifié quoi, et quand. Lecture seule via l'API. */
#[ORM\Entity]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: "is_granted('TICKET_VIEW', object.getTicket())"),
    ],
    normalizationContext: ['groups' => ['history:read']],
    order: ['createdAt' => 'ASC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['ticket' => 'exact'])]
class TicketHistory
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] #[Groups(['history:read'])]
    private ?int $id = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Ticket $ticket;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)] #[Groups(['history:read'])]
    private User $actor;
    #[ORM\Column(length: 30)] #[Groups(['history:read'])]
    private string $field;
    #[ORM\Column(nullable: true)] #[Groups(['history:read'])]
    private ?string $oldValue;
    #[ORM\Column(nullable: true)] #[Groups(['history:read'])]
    private ?string $newValue;
    #[ORM\Column] #[Groups(['history:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(Ticket $t, User $actor, string $field, ?string $old, ?string $new)
    {
        $this->ticket = $t; $this->actor = $actor; $this->field = $field;
        $this->oldValue = $old; $this->newValue = $new; $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTicket(): Ticket { return $this->ticket; }
    public function getActor(): User { return $this->actor; }
    public function getField(): string { return $this->field; }
    public function getOldValue(): ?string { return $this->oldValue; }
    public function getNewValue(): ?string { return $this->newValue; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
