<?php

namespace App\Entity;

use App\Repository\CalendarSubscriptionRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CalendarSubscriptionRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_CALENDAR_USER_NANNY', fields: ['user', 'nanny'])]
class CalendarSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 26, unique: true)]
    private string $publicId;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Nanny $nanny = null;
    #[ORM\Column(length: 64, unique: true)]
    private string $accessToken;
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $regeneratedAt = null;

    public function __construct() { $this->publicId = UlidGenerator::generate(); $this->accessToken = bin2hex(random_bytes(32)); $this->createdAt = new \DateTime(); }
    public function getId(): ?int { return $this->id; }
    public function getPublicId(): string { return $this->publicId; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getNanny(): ?Nanny { return $this->nanny; }
    public function setNanny(?Nanny $nanny): static { $this->nanny = $nanny; return $this; }
    public function getAccessToken(): string { return $this->accessToken; }
    public function regenerate(): static { $this->accessToken = bin2hex(random_bytes(32)); $this->regeneratedAt = new \DateTime(); return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getRegeneratedAt(): ?\DateTimeInterface { return $this->regeneratedAt; }
}
