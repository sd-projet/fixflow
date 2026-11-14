<?php
namespace App\User;

use ApiPlatform\Metadata\{ApiResource, Get, GetCollection, Patch, Post};
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\{EquatableInterface, PasswordAuthenticatedUserInterface, UserInterface};
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '"user"')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_ADMIN') or object == user"),
        new Post(security: "is_granted('ROLE_ADMIN')", processor: UserProcessor::class, validationContext: ['groups' => ['Default', 'user:create']]),
        new Patch(security: "is_granted('ROLE_ADMIN') or object == user", processor: UserProcessor::class),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']],
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    public const ROLES = ['ROLE_ADMIN', 'ROLE_DEV', 'ROLE_USER'];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    #[Groups(['user:read', 'ticket:read', 'comment:read', 'history:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\Email, Assert\NotBlank]
    #[Groups(['user:read', 'user:write', 'ticket:read'])]
    private string $email = '';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Groups(['user:read', 'user:write', 'ticket:read', 'comment:read', 'history:read'])]
    private string $fullName = '';

    /** ROLE_ADMIN | ROLE_DEV | ROLE_USER — modifiable uniquement par un admin (UserProcessor) */
    #[ORM\Column(type: 'json')]
    #[Assert\All([new Assert\Choice(choices: self::ROLES)])]
    #[Groups(['user:read', 'user:write'])]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column]
    private string $password = '';

    #[Assert\NotBlank(groups: ['user:create'])]
    #[Assert\Length(min: 8)]
    #[Groups(['user:write'])]
    private ?string $plainPassword = null;

    #[ORM\Column]
    #[Groups(['user:read', 'user:write'])]
    private bool $active = true;

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $e): static { $this->email = $e; return $this; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getFullName(): string { return $this->fullName; }
    public function setFullName(string $n): static { $this->fullName = $n; return $this; }
    public function getRoles(): array { return array_values(array_unique([...$this->roles, 'ROLE_USER'])); }
    public function setRoles(array $r): static { $this->roles = $r; return $this; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $p): static { $this->password = $p; return $this; }
    public function getPlainPassword(): ?string { return $this->plainPassword; }
    public function setPlainPassword(?string $p): static { $this->plainPassword = $p; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $a): static { $this->active = $a; return $this; }
    public function eraseCredentials(): void { $this->plainPassword = null; }

    /** Force la déconnexion d'une session dont le compte a été désactivé ou dont le mot de passe a changé. */
    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $user->getId() === $this->id
            && $user->isActive()
            && $user->getPassword() === $this->password;
    }
}
