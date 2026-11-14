<?php

namespace App\Ticket;

use App\User\User;
use Doctrine\ORM\Mapping as ORM;

/** Fichier joint à un incident. Le contenu est stocké hors de `public/`, sous un nom aléatoire. */
#[ORM\Entity]
class Attachment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Ticket $ticket;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false)]
    private User $uploader;
    #[ORM\Column(length: 255)]
    private string $originalName;
    #[ORM\Column(length: 64, unique: true)]
    private string $storedName;
    #[ORM\Column(length: 100)]
    private string $mimeType;
    #[ORM\Column]
    private int $size;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Ticket $ticket, User $uploader, string $originalName, string $storedName, string $mimeType, int $size)
    {
        $this->ticket = $ticket; $this->uploader = $uploader; $this->originalName = $originalName;
        $this->storedName = $storedName; $this->mimeType = $mimeType; $this->size = $size;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTicket(): Ticket { return $this->ticket; }
    public function getUploader(): User { return $this->uploader; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getStoredName(): string { return $this->storedName; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getSize(): int { return $this->size; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
