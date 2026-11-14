<?php

namespace App\Comment;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\{ApiFilter, ApiResource, Delete, Get, GetCollection, Patch, Post};
use App\Ticket\Ticket;
use App\User\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: "is_granted('TICKET_VIEW', object.getTicket())"),
        // À la création on choisit le ticket ; ensuite seul le contenu est modifiable (pas de déplacement de commentaire).
        new Post(denormalizationContext: ['groups' => ['comment:create']], processor: CommentProcessor::class),
        new Patch(security: "object.getAuthor() == user", denormalizationContext: ['groups' => ['comment:update']], processor: CommentProcessor::class),
        new Delete(security: "object.getAuthor() == user or is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['comment:read']],
    order: ['createdAt' => 'ASC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['ticket' => 'exact'])]
class Comment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] #[Groups(['comment:read'])]
    private ?int $id = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull] #[Groups(['comment:read', 'comment:create'])]
    private ?Ticket $ticket = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)] #[Groups(['comment:read'])]
    private ?User $author = null;
    #[ORM\Column(type: 'text')] #[Assert\NotBlank, Assert\Length(max: 5000)] #[Groups(['comment:read', 'comment:create', 'comment:update'])]
    private string $content = '';
    #[ORM\Column] #[Groups(['comment:read'])]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column(nullable: true)] #[Groups(['comment:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getTicket(): ?Ticket { return $this->ticket; }
    public function setTicket(Ticket $t): static { $this->ticket = $t; return $this; }
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(User $a): static { $this->author = $a; return $this; }
    public function getContent(): string { return $this->content; }
    public function setContent(string $c): static { $this->content = $c; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function markEdited(): static { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
