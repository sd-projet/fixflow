<?php
namespace App\Ticket;

use ApiPlatform\Doctrine\Orm\Filter\{BooleanFilter, DateFilter, OrderFilter, SearchFilter};
use ApiPlatform\Metadata\{ApiFilter, ApiResource, Delete, Get, GetCollection, Patch, Post};
use App\User\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: "is_granted('TICKET_VIEW', object)"),
        new Post(processor: TicketProcessor::class),
        new Patch(security: "is_granted('TICKET_EDIT', object)", processor: TicketProcessor::class),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['ticket:read']],
    denormalizationContext: ['groups' => ['ticket:write']],
    order: ['createdAt' => 'DESC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['reference' => 'partial', 'title' => 'partial', 'status' => 'exact', 'priority' => 'exact', 'category' => 'exact', 'assignee' => 'exact'])]
#[ApiFilter(TicketSearchFilter::class)]
#[ApiFilter(BooleanFilter::class, properties: ['archived'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'updatedAt'])]
class Ticket
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    #[Groups(['ticket:read'])]
    private ?int $id = null;
    #[ORM\Column(length: 30, unique: true)] #[Groups(['ticket:read'])]
    private string $reference = '';
    #[ORM\Column(length: 200)] #[Assert\NotBlank, Assert\Length(max: 200)] #[Groups(['ticket:read', 'ticket:write'])]
    private string $title = '';
    #[ORM\Column(type: 'text')] #[Assert\NotBlank] #[Groups(['ticket:read', 'ticket:write'])]
    private string $description = '';
    #[ORM\Column(enumType: Category::class)] #[Groups(['ticket:read', 'ticket:write'])]
    private Category $category = Category::Bug;
    #[ORM\Column(enumType: Priority::class)] #[Groups(['ticket:read', 'ticket:write'])]
    private Priority $priority = Priority::Normal;
    #[ORM\Column(enumType: TicketStatus::class)] #[Groups(['ticket:read', 'ticket:write'])]
    private TicketStatus $status = TicketStatus::New;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)] #[Groups(['ticket:read'])]
    private ?User $creator = null;
    #[ORM\ManyToOne] #[Groups(['ticket:read', 'ticket:write'])]
    private ?User $assignee = null;
    #[ORM\Column] #[Groups(['ticket:read'])]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column] #[Groups(['ticket:read'])]
    private \DateTimeImmutable $updatedAt;
    #[ORM\Column(nullable: true)] #[Groups(['ticket:read'])]
    private ?\DateTimeImmutable $resolvedAt = null;
    #[ORM\Column] #[Groups(['ticket:read', 'ticket:write'])]
    private bool $archived = false;

    public function __construct() { $this->createdAt = $this->updatedAt = new \DateTimeImmutable(); }

    /** Marque une activité sur le ticket (modification, nouveau commentaire…). */
    #[ORM\PreUpdate] public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }

    /** Utilisé uniquement par les données de démonstration. */
    public function setTimestamps(\DateTimeImmutable $createdAt, \DateTimeImmutable $updatedAt): static
    {
        $this->createdAt = $createdAt; $this->updatedAt = $updatedAt; return $this;
    }

    public function getId(): ?int { return $this->id; }
    public function getReference(): string { return $this->reference; }
    public function setReference(string $r): static { $this->reference = $r; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $t): static { $this->title = $t; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $d): static { $this->description = $d; return $this; }
    public function getCategory(): Category { return $this->category; }
    public function setCategory(Category $c): static { $this->category = $c; return $this; }
    public function getPriority(): Priority { return $this->priority; }
    public function setPriority(Priority $p): static { $this->priority = $p; return $this; }
    public function getStatus(): TicketStatus { return $this->status; }
    public function setStatus(TicketStatus $s): static { $this->status = $s; return $this; }
    public function getCreator(): ?User { return $this->creator; }
    public function setCreator(User $u): static { $this->creator = $u; return $this; }
    public function getAssignee(): ?User { return $this->assignee; }
    public function setAssignee(?User $u): static { $this->assignee = $u; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function getResolvedAt(): ?\DateTimeImmutable { return $this->resolvedAt; }
    public function setResolvedAt(?\DateTimeImmutable $d): static { $this->resolvedAt = $d; return $this; }
    public function isArchived(): bool { return $this->archived; }
    public function setArchived(bool $a): static { $this->archived = $a; return $this; }
}
