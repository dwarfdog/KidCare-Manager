<?php

namespace App\Entity;

use App\Repository\UserRepository;
use App\Service\UlidGenerator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['username'], message: 'Ce nom d\'utilisateur est deja utilise.')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est deja utilise.', ignoreNull: true)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 26, unique: true)]
    private string $publicId;

    #[ORM\Column(length: 180)]
    private ?string $username = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $emailVerified = false;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $emailVerificationTokenHash = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $emailVerificationRequestedAt = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastLoginAt = null;

    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    private array $dashboardPreferences = [];

    /** @var Collection<int, NannyAssignment> */
    #[ORM\OneToMany(targetEntity: NannyAssignment::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $nannyAssignments;

    /** @var Collection<int, Care> */
    #[ORM\OneToMany(targetEntity: Care::class, mappedBy: 'user')]
    private Collection $cares;

    /** @var Collection<int, MonthlyPayment> */
    #[ORM\OneToMany(targetEntity: MonthlyPayment::class, mappedBy: 'user')]
    private Collection $monthlyPayments;

    /** @var Collection<int, CareTemplate> */
    #[ORM\OneToMany(targetEntity: CareTemplate::class, mappedBy: 'user')]
    private Collection $careTemplates;

    /** @var Collection<int, NannyRate> */
    #[ORM\OneToMany(targetEntity: NannyRate::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $nannyRates;

    public function __construct()
    {
        $this->publicId = UlidGenerator::generate();
        $this->createdAt = new \DateTime();
        $this->nannyAssignments = new ArrayCollection();
        $this->cares = new ArrayCollection();
        $this->monthlyPayments = new ArrayCollection();
        $this->careTemplates = new ArrayCollection();
        $this->nannyRates = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): string
    {
        return $this->publicId;
    }

    public function setPublicId(string $publicId): static
    {
        $this->publicId = $publicId;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = trim($username);

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $email = $email !== null ? trim($email) : null;
        $this->email = $email !== '' ? $email : null;

        return $this;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function setEmailVerified(bool $emailVerified): static
    {
        $this->emailVerified = $emailVerified;

        if ($emailVerified) {
            $this->emailVerificationTokenHash = null;
        }

        return $this;
    }

    public function getEmailVerificationTokenHash(): ?string
    {
        return $this->emailVerificationTokenHash;
    }

    public function setEmailVerificationTokenHash(?string $emailVerificationTokenHash): static
    {
        $this->emailVerificationTokenHash = $emailVerificationTokenHash;

        return $this;
    }

    public function getEmailVerificationRequestedAt(): ?\DateTimeInterface
    {
        return $this->emailVerificationRequestedAt;
    }

    public function setEmailVerificationRequestedAt(?\DateTimeInterface $emailVerificationRequestedAt): static
    {
        $this->emailVerificationRequestedAt = $emailVerificationRequestedAt;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeInterface $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getDashboardPreferences(): array
    {
        return $this->dashboardPreferences;
    }

    /** @param array<string, mixed> $dashboardPreferences */
    public function setDashboardPreferences(array $dashboardPreferences): static
    {
        $this->dashboardPreferences = $dashboardPreferences;

        return $this;
    }

    /** @return Collection<int, NannyAssignment> */
    public function getNannyAssignments(): Collection
    {
        return $this->nannyAssignments;
    }

    public function getNannyAssignmentFor(Nanny $nanny): ?NannyAssignment
    {
        foreach ($this->nannyAssignments as $assignment) {
            if ($assignment->getNanny() === $nanny) {
                return $assignment;
            }
        }

        return null;
    }

    /** @return Collection<int, Nanny> */
    public function getNannies(): Collection
    {
        return new ArrayCollection(
            $this->nannyAssignments
                ->filter(static fn (NannyAssignment $assignment): bool => $assignment->getDetachedAt() === null)
                ->map(static fn (NannyAssignment $assignment): Nanny => $assignment->getNanny())
                ->toArray()
        );
    }

    public function addNannyAssignment(NannyAssignment $assignment): static
    {
        if (!$this->nannyAssignments->contains($assignment)) {
            $this->nannyAssignments->add($assignment);
            $assignment->setUser($this);
        }

        return $this;
    }

    public function removeNannyAssignment(NannyAssignment $assignment): static
    {
        $this->nannyAssignments->removeElement($assignment);

        return $this;
    }

    /** @return Collection<int, Care> */
    public function getCares(): Collection
    {
        return $this->cares;
    }

    /** @return Collection<int, MonthlyPayment> */
    public function getMonthlyPayments(): Collection
    {
        return $this->monthlyPayments;
    }

    /** @return Collection<int, CareTemplate> */
    public function getCareTemplates(): Collection
    {
        return $this->careTemplates;
    }

    /** @return Collection<int, NannyRate> */
    public function getNannyRates(): Collection
    {
        return $this->nannyRates;
    }
}
