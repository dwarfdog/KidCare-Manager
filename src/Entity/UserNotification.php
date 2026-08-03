<?php

namespace App\Entity;

use App\Repository\UserNotificationRepository;
use App\Service\UlidGenerator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserNotificationRepository::class)]
#[ORM\Index(columns: ['user_id', 'read_at', 'created_at'], name: 'IDX_NOTIFICATION_USER_STATE')]
#[ORM\UniqueConstraint(name: 'UNIQ_NOTIFICATION_USER_DEDUPE', fields: ['user', 'dedupeKey'])]
class UserNotification
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
    #[ORM\Column(length: 40)]
    private string $kind = 'information';
    #[ORM\Column(length: 180)]
    private string $title = '';
    #[ORM\Column(type: Types::TEXT)]
    private string $message = '';
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $link = null;
    #[ORM\Column(length: 190)]
    private string $dedupeKey = '';
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $readAt = null;
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct() { $this->publicId = UlidGenerator::generate(); $this->createdAt = new \DateTime(); }
    public function getId(): ?int { return $this->id; }
    public function getPublicId(): string { return $this->publicId; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getKind(): string { return $this->kind; }
    public function setKind(string $kind): static { $this->kind = trim($kind); return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = trim($title); return $this; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $message): static { $this->message = trim($message); return $this; }
    public function getLink(): ?string { return $this->link; }
    public function setLink(?string $link): static { $this->link = $link !== null && trim($link) !== '' ? trim($link) : null; return $this; }
    public function getDedupeKey(): string { return $this->dedupeKey; }
    public function setDedupeKey(string $dedupeKey): static { $this->dedupeKey = trim($dedupeKey); return $this; }
    public function getReadAt(): ?\DateTimeInterface { return $this->readAt; }
    public function markRead(): static { $this->readAt ??= new \DateTime(); return $this; }
    public function isRead(): bool { return $this->readAt !== null; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
